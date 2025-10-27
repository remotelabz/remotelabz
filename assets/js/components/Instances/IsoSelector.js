import React from 'react';
import { Form } from 'react-bootstrap';

const IsoSelector = ({ 
    deviceIsos, 
    bootWithIso, 
    setBootWithIso, 
    selectedIsoId, 
    setSelectedIsoId,
    instanceUuid 
}) => {
    if (!deviceIsos || deviceIsos.length === 0) {
        return null;
    }

    const handleCheckboxChange = (e) => {
        const checked = e.target.checked;
        setBootWithIso(checked);
        
        if (!checked) {
            setSelectedIsoId(null);
        } else if (!selectedIsoId && deviceIsos.length > 0) {
            // Sélectionner le premier ISO par défaut
            setSelectedIsoId(deviceIsos[0].id);
        }
    };

    const handleIsoChange = (e) => {
        const value = e.target.value;
        setSelectedIsoId(value ? parseInt(value) : null);
    };

    return (
        <div className="mb-3">
            <Form.Check
                type="checkbox"
                id={`boot-iso-${instanceUuid}`}
                label="Boot ISO"
                checked={bootWithIso}
                onChange={handleCheckboxChange}
            />
            
            {bootWithIso && (
                <Form.Group className="mt-2">
                    <Form.Label>Select ISO</Form.Label>
                    <Form.Control
                        as="select"
                        value={selectedIsoId || ''}
                        onChange={handleIsoChange}
                        required={bootWithIso}
                    >
                        <option value="">-- Select an ISO --</option>
                        {deviceIsos.map((iso) => (
                            <option key={iso.id} value={iso.id}>
                                {iso.name || iso.filename || `ISO ${iso.id}`}
                            </option>
                        ))}
                    </Form.Control>
                    {bootWithIso && !selectedIsoId && (
                        <Form.Text className="text-danger">
                            Please select an ISO to continue
                        </Form.Text>
                    )}
                </Form.Group>
            )}
        </div>
    );
};

export default IsoSelector;