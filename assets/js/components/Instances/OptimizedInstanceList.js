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

  //console.log("[OptimizedInstanceList]instance à afficher",instance);
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
    //console.log(`[DetailsModal] Action ${action} sur device ${deviceUuid}`);
    
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
  <div className="modal fade show" tabIndex="-1" role="dialog" style={{ display: 'block', backgroundColor: 'rgba(0,0,0,0.5)' }}>
    <div className="modal-dialog modal-lg" role="document">
      <div className="modal-content">
        {/* Header */}
        <div className="modal-header border-bottom">
          <div className="w-100">
            {/* First row: Instance info and close button */}
            <div className="d-flex justify-content-between align-items-center mb-2">
              <div>
                <h4 className="mb-0">{labInfo.name || 'Lab'}
                  &nbsp;<span className={`badge badge-${selectedInstance.state === 'created' ? 'success' : selectedInstance.state === 'creating' ? 'warning' : 'secondary'}`}>
                {selectedInstance.state}
                  </span>
                </h4>
                <small className="text-muted">Instance: {selectedInstance.uuid}</small>
                
              </div>
              
              <button type="button" className="close" onClick={onClose}>
                <span>&times;</span>
              </button>
            </div>
            
            {/* Second row: Action buttons centered */}
            <div className="d-flex justify-content-center gap-2">
              {(selectedInstance.state === 'stopped' || selectedInstance.state === 'error') && (
                <button
                  className="btn btn-sm btn-success"
                  onClick={() => onStateUpdate('start', selectedInstance.uuid, 'lab')}
                  disabled={sharedStates.startingInstances.has(`lab-${selectedInstance.uuid}`)}
                >
                  {sharedStates.startingInstances.has(`lab-${selectedInstance.uuid}`) ? <Spinner animation="border" size="sm" /> : <SVG name="play" />}
                  Start
                </button>
              )}
              
              {selectedInstance.state === 'started' && (
                <button
                  className="btn btn-sm btn-danger"
                  onClick={() => onStateUpdate('stop', selectedInstance.uuid, 'lab')}
                  disabled={sharedStates.stoppingInstances.has(`lab-${selectedInstance.uuid}`)}
                >
                  {sharedStates.stoppingInstances.has(`lab-${selectedInstance.uuid}`) ? <Spinner animation="border" size="sm" /> : <SVG name="stop" />}
                  Stop
                </button>
              )}

              {(selectedInstance.state === 'stopped' || selectedInstance.state === 'error') && (
                <button
                  className="btn btn-sm btn-warning"
                  onClick={() => onStateUpdate('reset', selectedInstance.uuid, 'lab')}
                  disabled={sharedStates.resettingInstances.has(`lab-${selectedInstance.uuid}`)}
                >
                  {sharedStates.resettingInstances.has(`lab-${selectedInstance.uuid}`) ? <Spinner animation="border" size="sm" /> : <SVG name="redo" />}
                  Reset
                </button>
              )}
            </div>
          </div>
        </div>

        {/* Body */}
        <div className="modal-body">
          <div className="content-body">
            {/* Lab Information Section - 2 columns */}
            <div className="row mb-4">
              <div className="col-md-6">
                <div className="card">
                  <div className="card-header">
                    <h5 className="mb-0">Owner</h5>
                  </div>
                  <div className="card-body">
                    <p className="text-muted mb-0">{labInfo.ownerName || 'N/A'}</p>
                  </div>
                </div>
              </div>

              <div className="col-md-6">
                <div className="card">
                  <div className="card-header">
                    <h5 className="mb-0">Informations</h5>
                  </div>
                  <div className="card-body">
                    <div className="mb-2">
                      <strong>Worker:</strong>
                      <p className="text-muted mb-0 small">{labInfo.workerIp || 'N/A'}</p>
                    </div>
                    {labInfo.network && (
                      <div>
                        <strong>Network:</strong>
                        <p className="text-muted mb-0 small">{labInfo.network}</p>
                      </div>
                    )}
                    {selectedInstance.createdAt && (
                      <div className="mt-2">
                        <strong>Created at:</strong>
                        <p className="text-muted mb-0 small">
                          {new Date(selectedInstance.createdAt).toLocaleString('fr-FR')}
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Device Instances Section */}
            {deviceInstances.length > 0 && (
              <div>
                <h5 className="mb-3">Devices ({deviceInstances.length})</h5>
                <div className="list-group">
                  {deviceInstances.map((deviceInstance) => (
                    <div key={deviceInstance.uuid} className="list-group-item">
                      <div className="d-flex justify-content-between align-items-start">
                        {/* Left side: Device name and state on first line, UUID on second */}
                        <div className="flex-grow-1">
                          <div className="d-flex align-items-center mb-1">
                            <h6 className="mb-0 mr-2">
                              {deviceInstance.device?.name || 'Appareil inconnu'}
                            </h6>
                            <span className={`badge badge-${deviceInstance.state === 'started' ? 'success' : deviceInstance.state === 'stopped' ? 'secondary' : 'warning'} badge-sm`}>
                              {deviceInstance.state}
                            </span>
                          </div>
                          <small className="text-muted d-block">
                            {deviceInstance.uuid}
                          </small>
                        </div>

                        {/* Right side: Action buttons */}
                        <div className="d-flex gap-1 ml-3">
                          {(deviceInstance.state === 'stopped' || deviceInstance.state === 'error') && (
                            <button
                              className="btn btn-sm btn-success"
                              onClick={() => handleDeviceAction(deviceInstance.uuid, 'start')}
                              disabled={deviceStates[deviceInstance.uuid] === 'start'}
                              title="Démarrer"
                            >
                              {deviceStates[deviceInstance.uuid] === 'start' ? <Spinner animation="border" size="sm" /> : <SVG name="play" />}
                            </button>
                          )}
                          
                          {deviceInstance.state === 'started' && (
                            <button
                              className="btn btn-sm btn-danger"
                              onClick={() => handleDeviceAction(deviceInstance.uuid, 'stop')}
                              disabled={deviceStates[deviceInstance.uuid] === 'stop'}
                              title="Arrêter"
                            >
                              {deviceStates[deviceInstance.uuid] === 'stop' ? <Spinner animation="border" size="sm" /> : <SVG name="stop" />}
                            </button>
                          )}
                          
                          {(deviceInstance.state === 'stopped' || deviceInstance.state === 'error') && (
                            <button
                              className="btn btn-sm btn-warning"
                              onClick={() => handleDeviceAction(deviceInstance.uuid, 'reset')}
                              disabled={deviceStates[deviceInstance.uuid] === 'reset'}
                              title="Réinitialiser"
                            >
                              {deviceStates[deviceInstance.uuid] === 'reset' ? <Spinner animation="border" size="sm" /> : <SVG name="redo" />}
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {deviceInstances.length === 0 && (
              <div className="alert alert-info">
                <p className="mb-0">Aucun périphérique dans cette instance</p>
              </div>
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="modal-footer">
          <button type="button" className="btn btn-secondary" onClick={onClose}>
            Close
          </button>
        </div>
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

  useEffect(() => {
        console.log('[OptimizedInstanceList] Instances changées, nettoyage du cache');
        
        // Créer un Set des UUIDs actuels
        const currentUuids = new Set(instances.map(i => i.uuid));
        
        // Nettoyer les entrées du cache qui ne sont plus dans la liste
        const newCache = new Map();
        currentUuids.forEach(uuid => {
          if (cacheRef.current.has(uuid)) {
            newCache.set(uuid, true);
          }
        });
        cacheRef.current = newCache;
        
        // Nettoyer aussi le state cache
        setSharedStates(prev => {
          const newLabCache = {};
          const newDeviceCache = {};
          
          currentUuids.forEach(uuid => {
            if (prev.labCache[uuid]) {
              newLabCache[uuid] = prev.labCache[uuid];
            }
            if (prev.deviceInstancesCache[uuid]) {
              newDeviceCache[uuid] = prev.deviceInstancesCache[uuid];
            }
          });
          
          return {
            ...prev,
            labCache: newLabCache,
            deviceInstancesCache: newDeviceCache
          };
        });
  }, [instances]);

  const handleLoadDetails = useCallback((uuid) => {
    if (cacheRef.current.has(uuid)) {
      //console.log(`[OptimizedInstanceList] Cache hit pour ${uuid}`);
      return;
    }

    //console.log(`[OptimizedInstanceList] Chargement des détails pour ${uuid}`);

    const instance = instances.find(i => i.uuid === uuid);
    if (!instance) {
      //console.warn(`[OptimizedInstanceList] Instance ${uuid} non trouvée`);
      return;
    }

    // Les données sont DÉJÀ dans l'instance reçue de AllInstancesList
    // Pas besoin d'un appel API supplémentaire !
    //console.log(`[OptimizedInstanceList] Données extraites pour ${uuid}:`, instance);
    
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
    //console.log(`[OptimizedInstanceList] handleStateUpdate - action: ${action}, uuid: ${uuid}, type: ${type}`);
    
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
    //console.log('[OptimizedInstanceList] Ouverture modal pour:', instance);
    setSelectedInstance(instance);
  }, []);

  const memoizedInstances = useMemo(() => instances, [instances]);

  if (!Array.isArray(memoizedInstances) || memoizedInstances.length === 0) {
    return <div className="virtualized-list-empty"><p>Aucune instance disponible</p></div>;
  }
  //console.log("[OptimizedInstanceList]:memoizedInstances avant le return ",memoizedInstances)
  return (
    <div className="virtualized-list-container">
      <div className="virtualized-list-header">
        <h2>Instances ({memoizedInstances.length})</h2>
      </div>

      <div className="virtualized-list-content">
        <List
          rowComponent={VirtualizedInstanceRow}
          rowCount={memoizedInstances.length}
          rowHeight={100}
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