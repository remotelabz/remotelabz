import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Badge, Button, Form, Spinner, Table } from 'react-bootstrap';
import { toast } from 'react-toastify';
import Remotelabz from '../API';

/**
 * Import wizard for an EVE-NG lab (.unl or .zip).
 *
 * Steps:
 *   1. server-side analysis (POST /api/labs/import/eve-ng/analyze);
 *   2. review window: topology + image table with their status;
 *   3. commit (POST /api/labs/import/eve-ng/commit) with the chosen decisions.
 *
 * The component is mounted by LabImporter as soon as a .unl/.zip file is dropped.
 */

const STATUS_META = {
    matched: { color: 'success', label: 'Exact match' },
    auto: { color: 'primary', label: 'Suggested equivalent' },
    missing: { color: 'danger', label: 'Not found' },
    not_applicable: { color: 'secondary', label: 'No image' },
};

const ACTIONS = [
    { value: 'reuse', label: 'Use an existing image' },
    { value: 'upload', label: 'Upload a file' },
    { value: 'url', label: 'Provide a URL' },
    { value: 'blank', label: 'Create a blank VM' },
    { value: 'skip', label: 'Skip (fix later)' },
];

/** Default decision for a given image according to its status / suggested action. */
function defaultDecision(image) {
    if (image.status === 'not_applicable') {
        return { action: 'skip' };
    }
    if (image.status === 'matched' || image.status === 'auto') {
        return { action: 'reuse', os_id: image.os_id ?? null };
    }

    const action = image.suggested_action && image.suggested_action !== 'not_applicable' ? image.suggested_action : 'reuse';

    return { action, os_id: null };
}

function statusMeta(status) {
    return STATUS_META[status] ?? { color: 'secondary', label: status };
}

/** Human readable byte count, e.g. 2097152 => "2 MB". */
function formatBytes(bytes) {
    if (!bytes || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    const decimals = unit > 0 && value < 10 ? 1 : 0;

    return `${value.toFixed(decimals)} ${units[unit]}`;
}

export default function EveNgImporter({ file, onCancel, onImported }) {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [preview, setPreview] = useState(null);
    const [decisions, setDecisions] = useState({});
    const [operatingSystems, setOperatingSystems] = useState([]);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        let cancelled = false;

        Remotelabz.operatingSystems.all()
            .then(response => {
                if (!cancelled) {
                    setOperatingSystems(response.data?.data ?? response.data ?? []);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setOperatingSystems([]);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        if (!file) {
            return;
        }

        let cancelled = false;

        setLoading(true);
        setError(null);

        Remotelabz.labs.analyzeEveNg(file)
            .then(response => {
                if (cancelled) {
                    return;
                }
                const data = response.data?.data ?? response.data;
                setPreview(data);

                const initial = {};
                (data.images || []).forEach(image => {
                    initial[image.eve_image] = defaultDecision(image);
                });
                setDecisions(initial);
            })
            .catch(err => {
                if (cancelled) {
                    return;
                }
                setError(err.response?.data?.error || 'Unable to analyze this file.');
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [file]);

    const images = preview?.images ?? [];
    const uploadLimit = {
        bytes: preview?.limits?.upload_max_bytes ?? 0,
        human: preview?.limits?.upload_max_human ?? '',
    };
    const unresolvedCount = images.filter(image => {
        const decision = decisions[image.eve_image] ?? {};
        return decision.action !== 'skip' && !['matched', 'auto', 'not_applicable'].includes(image.status);
    }).length;

    const updateDecision = useCallback((eveImage, patch) => {
        setDecisions(prev => ({
            ...prev,
            [eveImage]: { ...(prev[eveImage] ?? {}), ...patch },
        }));
    }, []);

    const handleUpload = useCallback((image, uploaded) => {
        if (!uploaded) {
            return;
        }
        updateDecision(image.eve_image, { filename: uploaded.filename, name: uploaded.originalName || image.eve_image });
    }, [updateDecision]);

    const submit = useCallback(() => {
        if (!file || !preview) {
            return;
        }

        setSubmitting(true);

        const mapping = Object.entries(decisions).map(([eveImage, decision]) => ({
            eve_image: eveImage,
            ...decision,
        }));

        Remotelabz.labs.commitEveNg(file, mapping)
            .then(response => {
                const data = response.data?.data ?? {};
                toast.success('The EVE-NG lab has been imported.');
                if (onImported) {
                    onImported(data);
                }
            })
            .catch(err => {
                toast.error(err.response?.data?.error || 'The lab import failed.');
            })
            .finally(() => {
                setSubmitting(false);
            });
    }, [file, preview, decisions, onImported]);

    if (loading) {
        return (
            <div className="text-center py-4">
                <Spinner animation="border" role="status" />
                <p className="mt-2">Analyzing the EVE-NG file…</p>
            </div>
        );
    }

    if (error) {
        return (
            <div>
                <Alert variant="danger">{error}</Alert>
                <Button variant="secondary" onClick={onCancel}>Back</Button>
            </div>
        );
    }

    if (!preview) {
        return null;
    }

    return (
        <div className="eve-ng-importer">
            <p className="mb-1">
                <strong>Lab:</strong> {preview.lab?.name}
            </p>
            <p className="text-muted">
                Devices: {preview.lab?.devices} · Links: {preview.lab?.links} · Annotations: {preview.lab?.textobjects}
                {unresolvedCount > 0 && <> · <span className="text-danger">{unresolvedCount} image(s) to resolve</span></>}
            </p>

            {(preview.warnings || []).map((warning, index) => (
                <Alert key={index} variant="warning" className="py-2 mb-2">{warning}</Alert>
            ))}

            <h6 className="mt-3">Device images</h6>
            <Table size="sm" responsive>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>EVE-NG image</th>
                        <th>RemoteLabz image</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {images.map(image => (
                        <ImageRow
                            key={image.eve_image}
                            image={image}
                            decision={decisions[image.eve_image] ?? {}}
                            operatingSystems={operatingSystems}
                            uploadLimit={uploadLimit}
                            onChange={updateDecision}
                            onUpload={handleUpload}
                        />
                    ))}
                </tbody>
            </Table>

            <div className="d-flex justify-content-between mt-3">
                <Button variant="secondary" onClick={onCancel} disabled={submitting}>Cancel</Button>
                <Button variant="success" onClick={submit} disabled={submitting}>
                    {submitting ? 'Importing…' : 'Import'}
                </Button>
            </div>
        </div>
    );
}

function ImageRow({ image, decision, operatingSystems, uploadLimit, onChange, onUpload }) {
    const meta = statusMeta(image.status);
    const [uploading, setUploading] = useState(false);
    const showResolution = decision.action !== 'reuse' || image.status === 'missing';

    const handleFileChange = useCallback(event => {
        const selected = event.target.files?.[0];
        // Reset the input so picking the same file again still fires onChange.
        event.target.value = '';

        if (!selected) {
            return;
        }

        // Pre-check the size against PHP's limit so an oversized file yields a
        // clear message instead of being silently dropped by PHP (which would
        // otherwise look like an empty upload).
        if (uploadLimit.bytes > 0 && selected.size > uploadLimit.bytes) {
            toast.error(`File is too large (${formatBytes(selected.size)}). Maximum upload size is ${uploadLimit.human}.`);
            return;
        }

        setUploading(true);
        Remotelabz.operatingSystems.upload(selected)
            .then(response => onUpload(image, response.data))
            .catch(error => {
                const data = error.response?.data;
                toast.error(data?.message || data?.error || 'The upload failed.');
            })
            .finally(() => setUploading(false));
    }, [image, onUpload, uploadLimit]);

    return (
        <>
            <tr>
                <td><Badge bg={meta.color}>{meta.label}</Badge></td>
                <td className="text-break"><code>{image.eve_image}</code></td>
                <td>
                    {['matched', 'auto'].includes(image.status)
                        ? <span>{image.os_name} <small className="text-muted">({image.os_image})</small></span>
                        : <span className="text-muted">—</span>}
                </td>
                <td>
                    {image.status === 'not_applicable' ? (
                        <span className="text-muted">native device</span>
                    ) : (
                        <Form.Select
                            size="sm"
                            value={decision.action ?? ''}
                            onChange={event => onChange(image.eve_image, { action: event.target.value })}
                        >
                            {ACTIONS.map(action => (
                                <option key={action.value} value={action.value}>{action.label}</option>
                            ))}
                        </Form.Select>
                    )}
                </td>
            </tr>
            {showResolution && image.status !== 'not_applicable' && (
                <tr>
                    <td colSpan={4} className="bg-body-tertiary">
                        <ResolutionFields
                            image={image}
                            decision={decision}
                            operatingSystems={operatingSystems}
                            uploadLimit={uploadLimit}
                            onChange={onChange}
                            onFileChange={handleFileChange}
                            uploading={uploading}
                        />
                    </td>
                </tr>
            )}
        </>
    );
}

function ResolutionFields({ image, decision, operatingSystems, uploadLimit, onChange, onFileChange, uploading }) {
    switch (decision.action) {
        case 'reuse': {
            const candidates = decision.candidates ?? image.candidates ?? [];
            const seen = new Set();
            const options = [];

            candidates.forEach(candidate => {
                if (candidate.id != null && !seen.has(candidate.id)) {
                    seen.add(candidate.id);
                    options.push({ id: candidate.id, label: `${candidate.name} (${candidate.image})` });
                }
            });

            (operatingSystems || []).forEach(os => {
                if (os.id != null && !seen.has(os.id)) {
                    seen.add(os.id);
                    options.push({ id: os.id, label: `${os.name} (${os.image ?? ''})` });
                }
            });

            return (
                <Form.Select
                    size="sm"
                    value={decision.os_id ?? ''}
                    onChange={event => onChange(image.eve_image, { os_id: Number(event.target.value) || null })}
                >
                    <option value="">— select an existing image —</option>
                    {options.map(option => (
                        <option key={option.id} value={option.id}>{option.label}</option>
                    ))}
                </Form.Select>
            );
        }
        case 'upload':
            return (
                <Form.Group>
                    <Form.Control size="sm" type="file" onChange={onFileChange} disabled={uploading} />
                    {uploading && <small className="text-muted">Uploading…</small>}
                    {decision.filename && <small className="text-muted d-block">File: {decision.filename}</small>}
                    {uploadLimit.human && (
                        <small className="text-muted d-block">Maximum upload size: {uploadLimit.human}.</small>
                    )}
                </Form.Group>
            );
        case 'url':
            return (
                <Form.Control
                    size="sm"
                    type="url"
                    placeholder="https://…"
                    value={decision.url ?? ''}
                    onChange={event => onChange(image.eve_image, { url: event.target.value })}
                />
            );
        case 'blank':
            return (
                <Form.Control
                    size="sm"
                    type="text"
                    placeholder="Disk size (e.g. 3GB)"
                    value={decision.flavorDisk ?? '3GB'}
                    onChange={event => onChange(image.eve_image, { flavorDisk: event.target.value })}
                />
            );
        case 'skip':
            return <span className="text-muted">The device will be created without a resolved image.</span>;
        default:
            return null;
    }
}
