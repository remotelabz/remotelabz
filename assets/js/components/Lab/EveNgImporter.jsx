import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Badge, Button, Form, Spinner, Table } from 'react-bootstrap';
import { toast } from 'react-toastify';
import Remotelabz from '../API';

/**
 * Assistant d'import d'un lab EVE-NG (.unl ou .zip).
 *
 * Enchaine :
 *   1. analyse serveur (POST /api/labs/import/eve-ng/analyze) ;
 *   2. fenetre de revue : topologie + tableau des images avec leur statut ;
 *   3. commit (POST /api/labs/import/eve-ng/commit) avec les decisions choisies.
 *
 * Le composant est monte par LabImporter des qu'un fichier .unl/.zip est depose.
 */

const STATUS_META = {
    matched: { color: 'success', label: 'Correspondance exacte' },
    auto: { color: 'primary', label: 'Equivalence proposee' },
    missing: { color: 'danger', label: 'Introuvable' },
    not_applicable: { color: 'secondary', label: 'Sans image' },
};

const ACTIONS = [
    { value: 'reuse', label: 'Choisir une image existante' },
    { value: 'upload', label: 'Televerser un fichier' },
    { value: 'url', label: 'Fournir une URL' },
    { value: 'blank', label: 'Creer une VM vierge' },
    { value: 'skip', label: 'Ignorer (a corriger plus tard)' },
];

/** Decision par defaut pour une image donnee selon son statut / action suggeree. */
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
                setError(err.response?.data?.error || 'Impossible d\'analyser ce fichier.');
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
                toast.success('Le lab EVE-NG a ete importe.');
                if (onImported) {
                    onImported(data);
                }
            })
            .catch(err => {
                toast.error(err.response?.data?.error || 'L\'import du lab a echoue.');
            })
            .finally(() => {
                setSubmitting(false);
            });
    }, [file, preview, decisions, onImported]);

    if (loading) {
        return (
            <div className="text-center py-4">
                <Spinner animation="border" role="status" />
                <p className="mt-2">Analyse du fichier EVE-NG…</p>
            </div>
        );
    }

    if (error) {
        return (
            <div>
                <Alert variant="danger">{error}</Alert>
                <Button variant="secondary" onClick={onCancel}>Retour</Button>
            </div>
        );
    }

    if (!preview) {
        return null;
    }

    return (
        <div className="eve-ng-importer">
            <p className="mb-1">
                <strong>Lab :</strong> {preview.lab?.name}
            </p>
            <p className="text-muted">
                Devices : {preview.lab?.devices} · Liens : {preview.lab?.links} · Annotations : {preview.lab?.textobjects}
                {unresolvedCount > 0 && <> · <span className="text-danger">{unresolvedCount} image(s) a resoudre</span></>}
            </p>

            {(preview.warnings || []).map((warning, index) => (
                <Alert key={index} variant="warning" className="py-2 mb-2">{warning}</Alert>
            ))}

            <h6 className="mt-3">Images des devices</h6>
            <Table size="sm" responsive>
                <thead>
                    <tr>
                        <th>Statut</th>
                        <th>Image EVE-NG</th>
                        <th>Image RemoteLabz</th>
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
                            onChange={updateDecision}
                            onUpload={handleUpload}
                        />
                    ))}
                </tbody>
            </Table>

            <div className="d-flex justify-content-between mt-3">
                <Button variant="secondary" onClick={onCancel} disabled={submitting}>Annuler</Button>
                <Button variant="success" onClick={submit} disabled={submitting}>
                    {submitting ? 'Import en cours…' : 'Importer'}
                </Button>
            </div>
        </div>
    );
}

function ImageRow({ image, decision, operatingSystems, onChange, onUpload }) {
    const meta = statusMeta(image.status);
    const [uploading, setUploading] = useState(false);
    const showResolution = decision.action !== 'reuse' || image.status === 'missing';

    const handleFileChange = useCallback(event => {
        const selected = event.target.files?.[0];
        if (!selected) {
            return;
        }

        setUploading(true);
        Remotelabz.operatingSystems.upload(selected)
            .then(response => onUpload(image, response.data))
            .catch(() => toast.error('Le televersement a echoue.'))
            .finally(() => setUploading(false));
    }, [image, onUpload]);

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
                        <span className="text-muted">device natif</span>
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
                    <td colSpan={4} className="bg-light">
                        <ResolutionFields
                            image={image}
                            decision={decision}
                            operatingSystems={operatingSystems}
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

function ResolutionFields({ image, decision, operatingSystems, onChange, onFileChange, uploading }) {
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
                    <option value="">— selectionner une image existante —</option>
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
                    {uploading && <small className="text-muted">Televersement…</small>}
                    {decision.filename && <small className="text-muted d-block">Fichier : {decision.filename}</small>}
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
                    placeholder="Taille du disque (ex. 3GB)"
                    value={decision.flavorDisk ?? '3GB'}
                    onChange={event => onChange(image.eve_image, { flavorDisk: event.target.value })}
                />
            );
        case 'skip':
            return <span className="text-muted">Le device sera cree sans image resolue.</span>;
        default:
            return null;
    }
}
