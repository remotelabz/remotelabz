import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, Badge, Button, Card, Form, Spinner } from 'react-bootstrap';
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
    { value: 'reuse', label: 'Use proposed existing image' },
    { value: 'reuse_other', label: 'Use another image' },
    { value: 'upload', label: 'Upload a file' },
    { value: 'url', label: 'Provide a URL' },
    { value: 'blank', label: 'Create a blank VM' },
    { value: 'skip', label: 'Skip (fix later)' },
];

/** Hypervisors able to host a disk image (a container or a virtual machine). */
const IMAGE_HYPERVISORS = ['qemu', 'lxc'];

function hypervisorNameOfOs(os) {
    return os?.hypervisor?.name ?? null;
}

function normalizeHypervisorName(name) {
    return typeof name === 'string' ? name.trim().toLowerCase() : '';
}

/** Default decision for a given image according to its status / suggested action. */
function defaultDecision(image) {
    if (image.status === 'not_applicable') {
        return { action: 'skip' };
    }

    const suggestedHypervisor = image.os_hypervisor?.name
        ?? image.hypervisor
        ?? null;

    // A proposal can exist even when the image file is missing from the worker
    // (the analysis then downgrades the status to `missing`): keep offering the
    // proposed OS so the user can accept it or upload the actual file.
    if (image.os_id != null || image.status === 'matched' || image.status === 'auto') {
        return {
            action: 'reuse',
            os_id: image.os_id ?? null,
            hypervisor: suggestedHypervisor,
        };
    }

    const suggested = image.suggested_action && image.suggested_action !== 'not_applicable'
        ? image.suggested_action
        : 'reuse';

    // No automatic match: "Use proposed existing image" would have nothing to
    // propose, so the manual image picker is selected instead.
    const action = suggested === 'reuse' ? 'reuse_other' : suggested;

    return {
        action,
        os_id: null,
        hypervisor: image.hypervisor ?? null,
        flavor_id: image.recommended_flavor_id ?? null,
    };
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
    const [deviceSelections, setDeviceSelections] = useState({});
    const [operatingSystems, setOperatingSystems] = useState([]);
    const [submitting, setSubmitting] = useState(false);

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

                // The analysis embeds the whole RemoteLabz image catalogue so
                // the manual picker works for teachers too (the
                // /api/operating-systems endpoint is reserved to editors).
                setOperatingSystems(data.operating_systems ?? []);

                const initial = {};
                (data.images || []).forEach(image => {
                    initial[image.eve_image] = defaultDecision(image);
                });
                setDecisions(initial);

                const initialDevices = {};
                (data.device_choices || []).forEach(choice => {
                    initialDevices[choice.eve_id] = choice.selected ?? '';
                });
                setDeviceSelections(initialDevices);
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
    const deviceChoices = preview?.device_choices ?? [];
    const deviceCandidates = preview?.device_candidates ?? [];
    const controlProtocols = preview?.control_protocols ?? [];
    const flavors = preview?.flavors ?? [];
    const uploadLimit = {
        bytes: preview?.limits?.upload_max_bytes ?? 0,
        human: preview?.limits?.upload_max_human ?? '',
    };

    // Hypervisors offered for image selection. Only qemu/lxc can host an OS
    // image; `natif`/`physical` are intentionally left out.
    const hypervisors = useMemo(() => {
        const found = new Map();

        (preview?.hypervisors ?? []).forEach(hypervisor => {
            const name = normalizeHypervisorName(hypervisor?.name);
            if (name && IMAGE_HYPERVISORS.includes(name)) {
                found.set(name, { ...hypervisor, name });
            }
        });

        (operatingSystems || []).forEach(os => {
            const name = normalizeHypervisorName(hypervisorNameOfOs(os));
            if (name && IMAGE_HYPERVISORS.includes(name) && !found.has(name)) {
                found.set(name, { id: os.hypervisor.id ?? null, name });
            }
        });

        return Array.from(found.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [preview, operatingSystems]);

    // Device summaries indexed by EVE-NG id, to display one block per device
    // under the image it uses.
    const devicesById = useMemo(() => {
        const found = new Map();

        (preview?.devices ?? []).forEach(device => {
            if (device.eve_id != null) {
                found.set(Number(device.eve_id), device);
            }
        });

        return found;
    }, [preview]);

    const unresolvedCount = images.filter(image => {
        const decision = decisions[image.eve_image] ?? {};

        if (decision.action === 'skip' || image.status === 'not_applicable') {
            return false;
        }
        if (['matched', 'auto'].includes(image.status) && decision.action === 'reuse') {
            return false;
        }

        switch (decision.action) {
            case 'reuse':
                return !(decision.os_id ?? image.os_id);
            case 'reuse_other':
                return !decision.os_id;
            case 'upload':
                return !decision.filename;
            case 'url':
                return !decision.url;
            case 'blank':
                return false;
            default:
                return true;
        }
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

        const devices = Object.entries(deviceSelections)
            .filter(([, template]) => !!template)
            .map(([eveId, template]) => ({ eve_id: Number(eveId), template }));

        Remotelabz.labs.commitEveNg(file, mapping, devices)
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
    }, [file, preview, decisions, deviceSelections, onImported]);

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
                {' · '}Images: {images.length}
                {unresolvedCount > 0 && <> · <span className="text-danger">{unresolvedCount} image(s) to resolve</span></>}
            </p>

            {(preview.warnings || []).map((warning, index) => (
                <Alert key={index} variant="warning" className="py-2 mb-2">{warning}</Alert>
            ))}

            <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-2">
                <h6 className="mb-0">Device images</h6>
                <div className="d-flex flex-wrap gap-1">
                    {Object.entries(STATUS_META).map(([status, meta]) => {
                        const count = images.filter(image => image.status === status).length;

                        return count > 0 ? (
                            <Badge key={status} bg={meta.color}>{count} {meta.label.toLowerCase()}</Badge>
                        ) : null;
                    })}
                </div>
            </div>
            <div className="row g-3">
                {images.map((image, index) => {
                    const devices = (image.used_by ?? []).map(eveId => devicesById.get(Number(eveId)) ?? {
                        eve_id: eveId,
                        name: `device-${eveId}`,
                        type: '',
                        icon: null,
                    });

                    return (
                        <div className="col-12 col-xxl-6" key={image.eve_image}>
                            <ImageCard
                                image={image}
                                index={index}
                                devices={devices}
                                decision={decisions[image.eve_image] ?? {}}
                                operatingSystems={operatingSystems}
                                hypervisors={hypervisors}
                                controlProtocols={controlProtocols}
                                flavors={flavors}
                                uploadLimit={uploadLimit}
                                onChange={updateDecision}
                                onUpload={handleUpload}
                            />
                        </div>
                    );
                })}
            </div>

            {deviceChoices.length > 0 && (
                <>
                    <h6 className="mt-4">Devices without image (VPCS/PC)</h6>
                    <p className="text-muted mb-2">
                        These EVE-NG nodes have no disk image. Pick the RemoteLabz device to create for each one.
                    </p>
                    <div className="row g-2">
                        {deviceChoices.map(choice => (
                            <div className="col-12 col-md-6 col-xxl-4" key={choice.eve_id}>
                                <div className="border rounded p-2 h-100">
                                    <div className="fw-semibold text-break mb-2">
                                        {choice.name}
                                        <span className="text-muted small"> · device #{choice.eve_id}</span>
                                    </div>
                                    <Form.Select
                                        size="sm"
                                        value={deviceSelections[choice.eve_id] ?? ''}
                                        onChange={event => setDeviceSelections(prev => ({
                                            ...prev,
                                            [choice.eve_id]: event.target.value,
                                        }))}
                                    >
                                        <option value="">— none —</option>
                                        {deviceCandidates.map(candidate => (
                                            <option key={candidate.template} value={candidate.template}>
                                                {candidate.name} ({candidate.type})
                                            </option>
                                        ))}
                                    </Form.Select>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}

            <div className="d-flex justify-content-between mt-3">
                <Button variant="secondary" onClick={onCancel} disabled={submitting}>Cancel</Button>
                <Button variant="success" onClick={submit} disabled={submitting}>
                    {submitting ? 'Importing…' : 'Import'}
                </Button>
            </div>
        </div>
    );
}

function ImageCard({ image, index, devices, decision, operatingSystems, hypervisors, controlProtocols, flavors, uploadLimit, onChange, onUpload }) {
    const meta = statusMeta(image.status);
    const [uploading, setUploading] = useState(false);
    const showResolution = decision.action !== 'reuse' || image.status === 'missing';
    const hasProposal = image.os_id != null || ['matched', 'auto'].includes(image.status);

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

    const handleActionChange = useCallback(event => {
        const action = event.target.value;
        const patch = { action };

        if (action === 'reuse') {
            patch.os_id = image.os_id ?? null;
            patch.hypervisor = image.os_hypervisor?.name ?? image.hypervisor ?? null;
        } else if (action === 'reuse_other') {
            // Force an explicit choice among the existing images, defaulting to
            // the hypervisor inferred from the EVE-NG node.
            patch.os_id = null;
            patch.hypervisor = decision.hypervisor ?? image.os_hypervisor?.name ?? image.hypervisor ?? null;
        }

        onChange(image.eve_image, patch);
    }, [decision.hypervisor, image, onChange]);

    const selectedOs = decision.os_id != null
        ? (operatingSystems || []).find(os => os.id === decision.os_id)
            ?? (image.candidates || []).find(candidate => candidate.id === decision.os_id)
            ?? null
        : null;
    const resolvedName = selectedOs?.name
        ?? (['matched', 'auto'].includes(image.status) ? image.os_name : null);
    const resolvedImage = selectedOs?.image
        ?? (['matched', 'auto'].includes(image.status) ? image.os_image : null);
    const targetHypervisor = decision.hypervisor
        ?? image.os_hypervisor?.name
        ?? image.hypervisor
        ?? null;

    const availableActions = hasProposal
        ? ACTIONS
        : ACTIONS.filter(action => action.value !== 'reuse');

    const deviceCount = devices.length;
    const showHypervisor = image.status !== 'not_applicable' && decision.action !== 'skip';

    // Summary of what will actually be imported for this image, following the
    // selected action rather than the automatic proposal only.
    let targetName = null;
    let targetDetail = null;

    switch (decision.action) {
        case 'reuse':
            targetName = resolvedName;
            targetDetail = resolvedImage;
            break;
        case 'reuse_other':
            targetName = selectedOs?.name ?? null;
            targetDetail = selectedOs?.image ?? null;
            break;
        case 'upload':
            targetName = decision.filename ? (decision.name || decision.filename) : null;
            targetDetail = decision.filename ? 'uploaded file' : null;
            break;
        case 'url':
            targetName = decision.url || null;
            targetDetail = decision.url ? 'remote URL' : null;
            break;
        case 'blank':
            targetName = 'Blank VM';
            targetDetail = `${decision.flavorDisk ?? '3GB'} disk`;
            break;
        case 'skip':
            targetName = image.status === 'not_applicable' ? 'Native device' : 'Skipped';
            targetDetail = image.status === 'not_applicable'
                ? 'nothing to import for this image'
                : 'the device will be created without a resolved image';
            break;
        default:
            targetName = resolvedName;
            targetDetail = resolvedImage;
    }

    return (
        <Card
            className="h-100 shadow-sm"
            style={{ borderLeft: `4px solid var(--bs-${meta.color})` }}
        >
            <Card.Header className="d-flex flex-wrap align-items-center gap-2">
                <Badge bg={meta.color}>{meta.label}</Badge>
                <span className="fw-semibold">Image {index + 1}</span>
                <code className="text-break flex-grow-1" style={{ minWidth: 0 }}>{image.eve_image}</code>
                <Badge bg="secondary">
                    {deviceCount} device{deviceCount === 1 ? '' : 's'}
                </Badge>
            </Card.Header>
            <Card.Body className="d-flex flex-column">
                <div className="text-uppercase text-muted fw-semibold mb-1" style={{ fontSize: '0.7rem' }}>
                    Imported devices
                </div>
                {deviceCount > 0 ? (
                    <div className="d-flex flex-wrap gap-1 mb-3">
                        {devices.map(device => (
                            <DeviceTile key={device.eve_id} device={device} />
                        ))}
                    </div>
                ) : (
                    <p className="text-muted small mb-3">No device uses this image.</p>
                )}

                <div className="rounded border bg-body-tertiary p-2 mb-3">
                    <div className="d-flex flex-wrap align-items-center gap-2">
                        <span className="text-uppercase text-muted fw-semibold" style={{ fontSize: '0.7rem' }}>
                            RemoteLabz image
                        </span>
                        {targetName ? (
                            <span className="fw-semibold text-break">{targetName}</span>
                        ) : (
                            <span className="text-muted">Not resolved yet</span>
                        )}
                        {targetDetail && (
                            ['reuse', 'reuse_other'].includes(decision.action)
                                ? <code className="small text-break">{targetDetail}</code>
                                : <span className="small text-muted text-break">{targetDetail}</span>
                        )}
                        {showHypervisor && (
                            <Badge bg="info">{targetHypervisor || 'default hypervisor'}</Badge>
                        )}
                    </div>
                    {image.reason && <div className="text-muted small mt-1">{image.reason}</div>}
                </div>

                <Form.Group className="mb-1">
                    <Form.Label className="mb-1"><small>Action</small></Form.Label>
                    {image.status === 'not_applicable' ? (
                        <div className="text-muted small">Native EVE-NG device (no image to import).</div>
                    ) : (
                        <Form.Select
                            size="sm"
                            value={decision.action ?? ''}
                            onChange={handleActionChange}
                        >
                            {availableActions.map(action => (
                                <option key={action.value} value={action.value}>{action.label}</option>
                            ))}
                        </Form.Select>
                    )}
                </Form.Group>

                {showResolution && image.status !== 'not_applicable' && (
                    <div className="border-top mt-2 pt-2">
                        <ResolutionFields
                            image={image}
                            decision={decision}
                            operatingSystems={operatingSystems}
                            hypervisors={hypervisors}
                            uploadLimit={uploadLimit}
                            onChange={onChange}
                            onFileChange={handleFileChange}
                            uploading={uploading}
                        />
                        {image.status === 'missing' && decision.action !== 'skip' && (
                            <FlavorField
                                image={image}
                                decision={decision}
                                flavors={flavors}
                                onChange={onChange}
                            />
                        )}
                    </div>
                )}

                {image.status !== 'not_applicable' && decision.action !== 'skip' && (
                    <ControlProtocolField
                        image={image}
                        index={index}
                        decision={decision}
                        controlProtocols={controlProtocols}
                        onChange={onChange}
                    />
                )}
            </Card.Body>
        </Card>
    );
}

/** Compact block identifying one EVE-NG device using the image of a card. */
function DeviceTile({ device }) {
    return (
        <div
            className="d-flex align-items-center gap-2 border rounded bg-body px-2 py-1"
            style={{ minWidth: '140px', maxWidth: '220px' }}
            title={`${device.name}${device.type ? ` (${device.type})` : ''}`}
        >
            {device.icon && (
                <img
                    src={`/build/editor/images/icons/${encodeURIComponent(device.icon)}`}
                    alt=""
                    width={20}
                    height={20}
                    className="flex-shrink-0"
                    onError={event => { event.currentTarget.style.display = 'none'; }}
                />
            )}
            <div className="overflow-hidden">
                <div className="small fw-semibold text-truncate">{device.name}</div>
                <div className="text-muted text-truncate" style={{ fontSize: '0.7rem' }}>
                    {device.type || 'device'}{device.eve_id != null ? ` · #${device.eve_id}` : ''}
                </div>
            </div>
        </div>
    );
}

/**
 * Flavor (RAM) selection for the device template created from an unresolved
 * image. The EVE-NG node `ram` value is shown as a recommendation.
 */
function FlavorField({ image, decision, flavors, onChange }) {
    const recommended = image.recommended_flavor_id ?? null;
    const value = decision.flavor_id ?? recommended ?? '';

    return (
        <Form.Group className="mt-2">
            <Form.Label className="mb-1">
                <small>
                    Device template memory
                    {image.ram > 0 && (
                        <span className="text-muted"> — recommended: {image.ram} MB (EVE-NG node)</span>
                    )}
                </small>
            </Form.Label>
            <Form.Select
                size="sm"
                value={value}
                onChange={event => onChange(image.eve_image, { flavor_id: Number(event.target.value) || null })}
            >
                <option value="">— default —</option>
                {(flavors || []).map(flavor => (
                    <option key={flavor.id} value={flavor.id}>
                        {flavor.name} ({flavor.memory} MB){flavor.id === recommended ? ' — recommended' : ''}
                    </option>
                ))}
            </Form.Select>
            <small className="text-muted d-block">
                A RemoteLabz device template will be created for this image and used by the imported devices.
            </small>
        </Form.Group>
    );
}

/** Human readable names for a list of control protocol ids. */
function protocolNames(ids, controlProtocols) {
    const byId = new Map((controlProtocols || []).map(protocol => [Number(protocol.id), protocol.name]));

    return (ids || [])
        .map(id => byId.get(Number(id)))
        .filter(Boolean);
}

/**
 * Per-image control protocol override. By default the protocol is deduced from
 * the EVE-NG console of the node(s) using the image; the user can select one or
 * several protocols (e.g. login + serial) for every imported device.
 */
function ControlProtocolField({ image, index, decision, controlProtocols, onChange }) {
    const automaticIds = (image.control_protocols ?? []).map(Number);
    const explicit = Array.isArray(decision.control_protocols)
        ? decision.control_protocols.map(Number)
        : null;
    const isAutomatic = explicit === null;
    const selectedIds = isAutomatic ? automaticIds : explicit;
    const automaticNames = protocolNames(automaticIds, controlProtocols);
    const automaticLabel = automaticNames.length > 0 ? automaticNames.join(', ') : 'none';

    const toggle = protocolId => {
        const next = selectedIds.includes(protocolId)
            ? selectedIds.filter(id => id !== protocolId)
            : [...selectedIds, protocolId];

        // Empty selection restores the automatic choice (the backend does the
        // same), so a device is never imported without a control protocol.
        onChange(image.eve_image, {
            control_protocols: next.length > 0 ? next : null,
        });
    };

    return (
        <Form.Group className="border-top mt-2 pt-2">
            <Form.Label className="mb-1 d-flex align-items-center justify-content-between">
                <small>Control protocols</small>
                {!isAutomatic && (
                    <Button
                        variant="link"
                        size="sm"
                        className="p-0"
                        onClick={() => onChange(image.eve_image, { control_protocols: null })}
                    >
                        Use automatic
                    </Button>
                )}
            </Form.Label>
            <div className="d-flex flex-wrap gap-3">
                {(controlProtocols || []).map(protocol => {
                    const protocolId = Number(protocol.id);

                    return (
                        <Form.Check
                            key={protocol.id}
                            type="checkbox"
                            id={`eve-control-protocol-${index}-${protocol.id}`}
                            label={protocol.name}
                            checked={selectedIds.includes(protocolId)}
                            onChange={() => toggle(protocolId)}
                        />
                    );
                })}
            </div>
            <small className="text-muted d-block">
                {isAutomatic ? `Automatic — EVE-NG console: ${automaticLabel}.` : 'Custom selection.'}
                {' '}Select one or more; clearing every box restores the automatic choice.
                {' '}Applied to every device imported from this image.
            </small>
        </Form.Group>
    );
}

function ResolutionFields({ image, decision, operatingSystems, hypervisors, uploadLimit, onChange, onFileChange, uploading }) {
    switch (decision.action) {
        case 'reuse': {
            // The automatic proposal is accepted as-is. To pick another image
            // (or another hypervisor), the user switches to "Use another image".
            const osId = decision.os_id ?? image.os_id;
            const os = (operatingSystems || []).find(candidate => candidate.id === osId) ?? null;
            const name = os?.name ?? image.os_name;
            const file = os?.image ?? image.os_image;
            const hypervisor = hypervisorNameOfOs(os)
                ?? image.os_hypervisor?.name
                ?? image.hypervisor;

            if (!name) {
                return (
                    <span className="text-muted">
                        No image was proposed automatically. Choose “Use another image”.
                    </span>
                );
            }

            return (
                <div className="d-flex flex-wrap align-items-center gap-2">
                    <span>{name} {file && <small className="text-muted">({file})</small>}</span>
                    {hypervisor && <Badge bg="info">{hypervisor}</Badge>}
                    {image.reason && <small className="text-muted">{image.reason}</small>}
                </div>
            );
        }
        case 'reuse_other':
            return (
                <ExistingImageFields
                    image={image}
                    decision={decision}
                    operatingSystems={operatingSystems}
                    hypervisors={hypervisors}
                    onChange={onChange}
                />
            );
        case 'upload':
            return (
                <div className="d-flex flex-column gap-2">
                    <HypervisorSelect
                        value={decision.hypervisor ?? image.hypervisor ?? ''}
                        hypervisors={hypervisors}
                        onChange={hypervisor => onChange(image.eve_image, { hypervisor: hypervisor || null })}
                    />
                    <Form.Group>
                        <Form.Control size="sm" type="file" onChange={onFileChange} disabled={uploading} />
                        {uploading && <small className="text-muted">Uploading…</small>}
                        {decision.filename && <small className="text-muted d-block">File: {decision.filename}</small>}
                        {uploadLimit.human && (
                            <small className="text-muted d-block">Maximum upload size: {uploadLimit.human}.</small>
                        )}
                    </Form.Group>
                </div>
            );
        case 'url':
            return (
                <div className="d-flex flex-column gap-2">
                    <HypervisorSelect
                        value={decision.hypervisor ?? image.hypervisor ?? ''}
                        hypervisors={hypervisors}
                        onChange={hypervisor => onChange(image.eve_image, { hypervisor: hypervisor || null })}
                    />
                    <Form.Control
                        size="sm"
                        type="url"
                        placeholder="https://…"
                        value={decision.url ?? ''}
                        onChange={event => onChange(image.eve_image, { url: event.target.value })}
                    />
                </div>
            );
        case 'blank':
            return (
                <div className="d-flex flex-column gap-2">
                    <HypervisorSelect
                        value={decision.hypervisor ?? image.hypervisor ?? ''}
                        hypervisors={hypervisors}
                        onChange={hypervisor => onChange(image.eve_image, { hypervisor: hypervisor || null })}
                    />
                    <Form.Control
                        size="sm"
                        type="text"
                        placeholder="Disk size (e.g. 3GB)"
                        value={decision.flavorDisk ?? '3GB'}
                        onChange={event => onChange(image.eve_image, { flavorDisk: event.target.value })}
                    />
                </div>
            );
        case 'skip':
            return <span className="text-muted">The device will be created without a resolved image.</span>;
        default:
            return null;
    }
}

/** Hypervisor picker (qemu VM or lxc container) shared by the resolution fields. */
function HypervisorSelect({ value, hypervisors, onChange }) {
    return (
        <Form.Group>
            <Form.Label className="mb-1"><small>Hypervisor</small></Form.Label>
            <Form.Select
                size="sm"
                value={normalizeHypervisorName(value)}
                onChange={event => onChange(event.target.value)}
            >
                <option value="">— default —</option>
                {(hypervisors || []).map(hypervisor => {
                    const name = normalizeHypervisorName(hypervisor.name);

                    return (
                        <option key={hypervisor.id ?? name} value={name}>
                            {hypervisor.name}
                        </option>
                    );
                })}
            </Form.Select>
        </Form.Group>
    );
}

/**
 * "Use another image": pick the hypervisor then any existing OperatingSystem
 * matching it. Suggested candidates from the analysis are listed first.
 */
function ExistingImageFields({ image, decision, operatingSystems, hypervisors, onChange }) {
    const selectedHypervisor = normalizeHypervisorName(decision.hypervisor ?? image.hypervisor ?? '');
    const candidates = image.candidates ?? [];
    const candidateIds = new Set(candidates.map(candidate => candidate.id));
    const optionsById = new Map();

    (operatingSystems || []).forEach(os => {
        if (os.id != null) {
            optionsById.set(os.id, os);
        }
    });
    // Keep the analysis suggestions available even when the OS list returned
    // by the API is paginated and does not include them.
    candidates.forEach(candidate => {
        if (candidate.id != null && !optionsById.has(candidate.id)) {
            optionsById.set(candidate.id, candidate);
        }
    });

    // Only offer the images hosted by the selected hypervisor: switching the
    // hypervisor updates the list, and picking an image aligns the hypervisor
    // on the decision. The full catalogue is embedded in the analysis so this
    // works for teachers too (the /api/operating-systems endpoint is reserved
    // to editors).
    const options = Array.from(optionsById.values())
        .filter(os => os.id != null)
        .filter(os => {
            const name = normalizeHypervisorName(hypervisorNameOfOs(os));

            if (selectedHypervisor) {
                return name === selectedHypervisor;
            }

            return !name || IMAGE_HYPERVISORS.includes(name);
        })
        .sort((a, b) => {
            const aSuggested = candidateIds.has(a.id) ? 0 : 1;
            const bSuggested = candidateIds.has(b.id) ? 0 : 1;

            return aSuggested - bSuggested || (a.name || '').localeCompare(b.name || '');
        });

    return (
        <div className="d-flex flex-column gap-2">
            <HypervisorSelect
                value={selectedHypervisor}
                hypervisors={hypervisors}
                onChange={hypervisor => onChange(image.eve_image, {
                    hypervisor: hypervisor || null,
                    os_id: null,
                })}
            />
            <Form.Group>
                <Form.Label className="mb-1"><small>Existing image</small></Form.Label>
                <Form.Select
                    size="sm"
                    value={decision.os_id ?? ''}
                    onChange={event => {
                        const osId = Number(event.target.value) || null;
                        const os = options.find(candidate => candidate.id === osId) ?? null;

                        onChange(image.eve_image, {
                            os_id: osId,
                            hypervisor: hypervisorNameOfOs(os) ?? decision.hypervisor ?? null,
                        });
                    }}
                >
                    <option value="">— select an existing image —</option>
                    {options.map(os => (
                        <option key={os.id} value={os.id}>
                            {os.name}{os.image ? ` (${os.image})` : ''}
                        </option>
                    ))}
                </Form.Select>
                {options.length === 0 && (
                    <small className="text-danger d-block">No existing image is available for this hypervisor.</small>
                )}
                <small className="text-muted d-block">
                    Only images matching the selected hypervisor are listed.
                </small>
            </Form.Group>
        </div>
    );
}
