import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { List } from 'react-window';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import { ListGroupItem, Button, Spinner, Modal } from 'react-bootstrap';


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
                <div class="row">
                    <div className="w-75">
                        <h2 className="mb-0">{labInfo.name || 'Lab'}</h2>
                        <small className="text-muted">Instance: {selectedInstance.uuid}</small>
                    </div>
                    <div className="w-25">
                          <span className="mr-2">Lab stated:</span>
                          <span className={`badge badge-${selectedInstance.state === 'created' ? 'success' : selectedInstance.state === 'creating' ? 'warning' : 'secondary'}`}>
                            {selectedInstance.state}
                          </span>
                          <button type="button" className="close" onClick={onClose}>
                            <span>&times;</span>
                          </button>
                    </div>
                </div>
                <div class="row">
                    <Button 
                      className="ml-3" 
                      variant="warning" 
                      title="Reset device" 
                      onClick={() => onStateUpdate('reset', selectedInstance.uuid, 'lab')}
                      disabled={sharedStates.resettingInstances.has(`lab-${selectedInstance.uuid}`)}
                    >
                      {<SVG name="redo" />}
                    </Button>

                    <Button 
                      className="ml-3" 
                      variant="success" 
                      title="Start device" 
                      onClick={() => onStateUpdate('start', selectedInstance.uuid, 'lab')}
                      disabled={sharedStates.startingInstances.has(`lab-${selectedInstance.uuid}`)}
                    >
                      {<SVG name="play" />}
                    </Button>
                    <Button 
                      className="ml-3" 
                      variant="danger" 
                      title="Stop device" 
                      onClick={() => onStateUpdate('stop', selectedInstance.uuid, 'lab')}
                      disabled={sharedStates.stoppingInstances.has(`lab-${selectedInstance.uuid}`)}
                    >
                      {<SVG name="stop" />}
                    </Button>
                </div>  
              </div>
              <div className="modal-body">            
              
                <div className="content-body">
                  <div className="row">
                        <div className="col-md-6">
                          <div className="card">
                            <div className="card-header">
                              <h5 className="mb-0">Lab informations</h5>
                            </div>
                            <div className="card-body">
                              <div className="mb-3">
                                <strong>Propriétaire:</strong>
                                <p className="text-muted mb-0">{labInfo.ownerName || 'N/A'}</p>
                              </div>
                              <div className="mb-3">
                                <strong>Worker:</strong>
                                <p className="text-muted mb-0">{labInfo.workerIp || 'N/A'}</p>
                              </div>
                              {selectedInstance.createdAt && (
                                <div className="mb-3">
                                  <strong>Créé le:</strong>
                                  <p className="text-muted mb-0">
                                    {new Date(selectedInstance.createdAt).toLocaleString('fr-FR')}
                                  </p>
                                </div>
                              )}
                              {labInfo.network && (
                                <div className="mb-3">
                                  <strong>Réseau:</strong>
                                  <p className="text-muted mb-0">{labInfo.network}</p>
                                </div>
                              )}
                            </div>
                          </div>
                        </div>
                  </div>
                </div>
                  

                {deviceInstances.length === 0 && (
                  <div className="modal-section">
                    <p>Aucun périphérique dans cette instance</p>
                  </div>
                )}
              </div>

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