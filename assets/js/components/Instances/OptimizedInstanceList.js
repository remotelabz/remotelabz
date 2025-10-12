import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { List } from 'react-window';
import Remotelabz from '../API';

function VirtualizedInstanceRow(props) {
  const { 
    index, 
    style, 
    instances,
    onLoadDetails,
    onStateUpdate,
    openDetailsModal,
    sharedStates
  } = props;
  
  const instance = instances?.[index];
  
  if (!instance) {
    return <div style={style}>Chargement...</div>;
  }

  useEffect(() => {
    onLoadDetails(instance.uuid);
  }, [instance.uuid, onLoadDetails]);

  const labInfo = sharedStates.labCache[instance.uuid] || {};
  const isStarting = sharedStates.startingInstances.has(`lab-${instance.uuid}`);
  const isStopping = sharedStates.stoppingInstances.has(`lab-${instance.uuid}`);
  const isResetting = sharedStates.resettingInstances.has(`lab-${instance.uuid}`);

  console.log("[OptimizedInstanceList]instance à afficher",instance);
  return (
    <div style={style} className="virtualized-instance-row">
      <div className="instance-info">
        <div className="instance-name">
          {labInfo.name || 'Chargement...'}
          <span className={`instance-state-badge state-${instance.state}`}>
            {instance.state}
          </span>
        </div>
        <div className="instance-uuid">{instance.uuid}</div>
        <div className="instance-meta">
          <span>Propriétaire: {labInfo.ownerName || 'N/A'}</span>
          <span className="ml-2">Worker: {labInfo.workerIp || 'N/A'}</span>
        </div>
      </div>

      <div className="instance-actions">
        {(instance.state === 'stopped' || instance.state === 'error') && (
          <button
            className="btn-start"
            onClick={() => onStateUpdate('start', instance.uuid, 'lab')}
            disabled={isStarting}
            title="Démarrer lab"
          >
            {isStarting ? <span className="loading-spinner"></span> : '▶'}
          </button>
        )}
        
        {instance.state === 'started' && (
          <button
            className="btn-stop"
            onClick={() => onStateUpdate('stop', instance.uuid, 'lab')}
            disabled={isStopping}
            title="Arrêter lab"
          >
            {isStopping ? <span className="loading-spinner"></span> : '⏹'}
          </button>
        )}

        {(instance.state === 'stopped' || instance.state === 'error') && (
          <button
            className="btn-reset"
            onClick={() => onStateUpdate('reset', instance.uuid, 'lab')}
            disabled={isResetting}
            title="Réinitialiser lab"
          >
            {isResetting ? <span className="loading-spinner"></span> : '↻'}
          </button>
        )}

        <button
          className="btn-details"
          onClick={() => openDetailsModal(instance)}
        >
          Détails
        </button>
      </div>
    </div>
  );
}

function DetailsModal({ selectedInstance, onClose, sharedStates, onStateUpdate }) {
  if (!selectedInstance) return null;

  const labInfo = sharedStates.labCache[selectedInstance.uuid] || {};
  const deviceInstances = sharedStates.deviceInstancesCache[selectedInstance.uuid] || [];
  
  const [expandedDevice, setExpandedDevice] = useState(null);
  const [deviceStates, setDeviceStates] = useState({});

  const handleDeviceAction = useCallback((deviceUuid, action) => {
    console.log(`[DetailsModal] Action ${action} sur device ${deviceUuid}`);
    
    setDeviceStates(prev => ({
      ...prev,
      [deviceUuid]: action
    }));
    
    onStateUpdate(action, deviceUuid, 'device');
    
    setTimeout(() => {
      setDeviceStates(prev => ({
        ...prev,
        [deviceUuid]: null
      }));
    }, 1500);
  }, [onStateUpdate]);

  return (
    <div className="virtualized-details-modal">
      <div className="modal-content">
        <div className="modal-header">
          <h2>{labInfo.name || 'Lab'} - {selectedInstance.state}</h2>
        </div>

        <div className="modal-section">
          <strong>UUID Lab:</strong>
          <p>{selectedInstance.uuid}</p>
        </div>

        <div className="modal-section">
          <strong>Propriétaire:</strong>
          <p>{labInfo.ownerName || 'N/A'}</p>
        </div>

        <div className="modal-section">
          <strong>Worker IP:</strong>
          <p>{labInfo.workerIp || 'N/A'}</p>
        </div>

        {selectedInstance.createdAt && (
          <div className="modal-section">
            <strong>Créé le:</strong>
            <p>{new Date(selectedInstance.createdAt).toLocaleString('fr-FR')}</p>
          </div>
        )}

        {labInfo.network && (
          <div className="modal-section">
            <strong>Réseau:</strong>
            <p>{labInfo.network}</p>
          </div>
        )}

        {/* Section Device Instances */}
        {deviceInstances.length > 0 && (
          <div className="device-instances-container">
            <h4>Instances de périphériques ({deviceInstances.length})</h4>
            <div className="devices-list">
              {deviceInstances.map((deviceInstance) => (
                <div key={deviceInstance.uuid} className="device-item">
                  <div className="device-header">
                    <div className="device-info">
                      <span className="device-name">
                        {deviceInstance.device?.name || 'Appareil inconnu'}
                      </span>
                      <span className={`device-state-badge state-${deviceInstance.state}`}>
                        {deviceInstance.state}
                      </span>
                    </div>
                    <button 
                      className="expand-btn"
                      onClick={() => setExpandedDevice(expandedDevice === deviceInstance.uuid ? null : deviceInstance.uuid)}
                    >
                      {expandedDevice === deviceInstance.uuid ? '▼' : '▶'}
                    </button>
                  </div>

                  {expandedDevice === deviceInstance.uuid && (
                    <div className="device-details">
                      <div className="detail-row">
                        <strong>UUID:</strong>
                        <span>{deviceInstance.uuid}</span>
                      </div>
                      <div className="detail-row">
                        <strong>Processeurs:</strong>
                        <span>{deviceInstance.nbCpu || 'N/A'}</span>
                      </div>
                      {deviceInstance.nbCore && (
                        <div className="detail-row">
                          <strong>Cores:</strong>
                          <span>{deviceInstance.nbCore}</span>
                        </div>
                      )}
                      {deviceInstance.nbSocket && (
                        <div className="detail-row">
                          <strong>Sockets:</strong>
                          <span>{deviceInstance.nbSocket}</span>
                        </div>
                      )}

                      {/* Actions pour deviceInstance */}
                      <div className="device-actions">
                        {(deviceInstance.state === 'stopped' || deviceInstance.state === 'error') && (
                          <button
                            className="btn-start-device"
                            onClick={() => handleDeviceAction(deviceInstance.uuid, 'start')}
                            disabled={deviceStates[deviceInstance.uuid] === 'start'}
                          >
                            {deviceStates[deviceInstance.uuid] === 'start' ? '...' : 'Démarrer'}
                          </button>
                        )}
                        {deviceInstance.state === 'started' && (
                          <button
                            className="btn-stop-device"
                            onClick={() => handleDeviceAction(deviceInstance.uuid, 'stop')}
                            disabled={deviceStates[deviceInstance.uuid] === 'stop'}
                          >
                            {deviceStates[deviceInstance.uuid] === 'stop' ? '...' : 'Arrêter'}
                          </button>
                        )}
                        {(deviceInstance.state === 'stopped' || deviceInstance.state === 'error') && (
                          <button
                            className="btn-reset-device"
                            onClick={() => handleDeviceAction(deviceInstance.uuid, 'reset')}
                            disabled={deviceStates[deviceInstance.uuid] === 'reset'}
                          >
                            {deviceStates[deviceInstance.uuid] === 'reset' ? '...' : 'Réinitialiser'}
                          </button>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {deviceInstances.length === 0 && (
          <div className="modal-section">
            <p>Aucun périphérique dans cette instance</p>
          </div>
        )}

        <div className="modal-actions">
          <button className="btn-close" onClick={onClose}>
            Fermer
          </button>
        </div>
      </div>
    </div>
  );
}

export default function OptimizedInstanceList({ 
  instances = [], 
  user = {},
  onStateUpdate: onStateUpdateProp = () => {}
}) {
  const [selectedInstance, setSelectedInstance] = useState(null);
  const [sharedStates, setSharedStates] = useState({
    labCache: {},
    deviceInstancesCache: {},
    startingInstances: new Set(),
    stoppingInstances: new Set(),
    resettingInstances: new Set()
  });

  const cacheRef = useRef(new Map());

  const handleLoadDetails = useCallback((uuid) => {
    if (cacheRef.current.has(uuid)) {
      console.log(`[OptimizedInstanceList] Cache hit pour ${uuid}`);
      return;
    }

    console.log(`[OptimizedInstanceList] Chargement des détails pour ${uuid}`);

    const instance = instances.find(i => i.uuid === uuid);
    if (!instance) {
      console.warn(`[OptimizedInstanceList] Instance ${uuid} non trouvée`);
      return;
    }

    // Les données sont DÉJÀ dans l'instance reçue de AllInstancesList
    // Pas besoin d'un appel API supplémentaire !
    console.log(`[OptimizedInstanceList] Données extraites pour ${uuid}:`, instance);
    
    const labInfo = instance.lab || {};
    const ownerInfo = instance.owner || {};
    
    cacheRef.current.set(uuid, true);
    
    setSharedStates(prev => ({
      ...prev,
      labCache: { 
        ...prev.labCache, 
        [uuid]: {
          name: labInfo.name || 'Lab inconnu',
          ownerName: ownerInfo.name || ownerInfo.email || 'N/A',
          workerIp: instance.workerIp || 'N/A',
          network: instance.network?.ip?.addr || 'N/A',
          state: instance.state
        }
      },
      deviceInstancesCache: {
        ...prev.deviceInstancesCache,
        [uuid]: Array.isArray(instance.deviceInstances) ? instance.deviceInstances : []
      }
    }));
  }, [instances]);

  const handleStateUpdate = useCallback((action, uuid, type = 'device') => {
    console.log(`[OptimizedInstanceList] handleStateUpdate - action: ${action}, uuid: ${uuid}, type: ${type}`);
    
    if (type === 'lab') {
      if (action === 'start') {
        setSharedStates(prev => ({
          ...prev,
          startingInstances: new Set([...prev.startingInstances, `lab-${uuid}`])
        }));
      } else if (action === 'stop') {
        setSharedStates(prev => ({
          ...prev,
          stoppingInstances: new Set([...prev.stoppingInstances, `lab-${uuid}`])
        }));
      } else if (action === 'reset') {
        setSharedStates(prev => ({
          ...prev,
          resettingInstances: new Set([...prev.resettingInstances, `lab-${uuid}`])
        }));
      }
    }
    
    onStateUpdateProp(action, uuid);
    
    setTimeout(() => {
      setSharedStates(prev => {
        const newStates = { ...prev };
        newStates.startingInstances = new Set([...prev.startingInstances].filter(id => id !== `lab-${uuid}` && id !== uuid));
        newStates.stoppingInstances = new Set([...prev.stoppingInstances].filter(id => id !== `lab-${uuid}` && id !== uuid));
        newStates.resettingInstances = new Set([...prev.resettingInstances].filter(id => id !== `lab-${uuid}` && id !== uuid));
        return newStates;
      });
    }, 1500);
  }, [onStateUpdateProp]);

  const openDetailsModal = useCallback((instance) => {
    console.log('[OptimizedInstanceList] Ouverture modal pour:', instance);
    setSelectedInstance(instance);
  }, []);

  const memoizedInstances = useMemo(() => instances, [instances]);

  if (!Array.isArray(memoizedInstances) || memoizedInstances.length === 0) {
    return <div className="virtualized-list-empty"><p>Aucune instance disponible</p></div>;
  }
console.log("[OptimizedInstanceList]:memoizedInstances avant le return ",memoizedInstances)
  return (
    <div className="virtualized-list-container">
      <div className="virtualized-list-header">
        <h2>Instances ({memoizedInstances.length})</h2>
      </div>

      <div className="virtualized-list-content">
        <List
          rowComponent={VirtualizedInstanceRow}
          rowCount={memoizedInstances.length}
          rowHeight={90}
          width="100%"
          height={window.innerHeight - 200}
          rowProps={{
            instances: memoizedInstances,
            onLoadDetails: handleLoadDetails,
            onStateUpdate: handleStateUpdate,
            openDetailsModal: openDetailsModal,
            sharedStates: sharedStates
          }}
        />
      </div>

      <DetailsModal
        selectedInstance={selectedInstance}
        onClose={() => setSelectedInstance(null)}
        sharedStates={sharedStates}
        onStateUpdate={handleStateUpdate}
      />
    </div>
  );
}