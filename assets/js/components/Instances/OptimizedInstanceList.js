import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { List } from 'react-window';
import Remotelabz from '../API';

function VirtualizedInstanceRow({ 
  index, 
  style, 
  data,
  onLoadDetails,
  onStateUpdate,
  openDetailsModal,
  sharedStates 
}) {
  const instance = data?.[index];

  // Guard: if instance is missing, render nothing (react-window may render more slots)
  useEffect(() => {
    if (instance && instance.uuid) {
      onLoadDetails(instance.uuid);
    }
  }, [instance?.uuid, onLoadDetails]);

  if (!instance) {
    return null;
  }

  const deviceName = sharedStates.deviceCache[instance.uuid]?.name || 'Chargement...';
  const isStarting = sharedStates.startingInstances.has(instance.uuid);
  const isStopping = sharedStates.stoppingInstances.has(instance.uuid);

  return (
    <div style={style} className="virtualized-instance-row">
      <div className="instance-info">
        <div className="instance-name">
          {deviceName}
          <span className={`instance-state-badge state-${instance.state}`}>
            {instance.state}
          </span>
        </div>
        <div className="instance-uuid">{instance.uuid}</div>
      </div>

      <div className="instance-actions">
        {instance.state === 'stopped' && (
          <button
            className="btn-start"
            onClick={() => onStateUpdate('start', instance.uuid)}
            disabled={isStarting}
          >
            {isStarting ? <span className="loading-spinner"></span> : '▶'}
          </button>
        )}
        
        {instance.state === 'started' && (
          <button
            className="btn-stop"
            onClick={() => onStateUpdate('stop', instance.uuid)}
            disabled={isStopping}
          >
            {isStopping ? <span className="loading-spinner"></span> : '⏹'}
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

function DetailsModal({ selectedInstance, onClose, logs, deviceCache }) {
  if (!selectedInstance) return null;

  const instanceLogs = logs[selectedInstance.uuid] || [];
  const deviceInfo = deviceCache[selectedInstance.uuid] || {};

  return (
    <div className="virtualized-details-modal">
      <div className="modal-content">
        <h2>{deviceInfo.name || 'Instance'} - {selectedInstance.state}</h2>
        
        <div className="modal-section">
          <strong>UUID:</strong>
          <p>{selectedInstance.uuid}</p>
        </div>

        {deviceInfo.id && (
          <div className="modal-section">
            <strong>Device ID:</strong>
            <p>{deviceInfo.id}</p>
          </div>
        )}

        {selectedInstance.createdAt && (
          <div className="modal-section">
            <strong>Créé le:</strong>
            <p>{new Date(selectedInstance.createdAt).toLocaleString('fr-FR')}</p>
          </div>
        )}

        {selectedInstance.workerIp && (
          <div className="modal-section">
            <strong>Worker IP:</strong>
            <p>{selectedInstance.workerIp}</p>
          </div>
        )}
        
        {instanceLogs.length > 0 && (
          <div className="logs-container">
            <h4>Logs</h4>
            <pre>
              {instanceLogs.map((log, idx) => (
                <code key={idx}>[{log.createdAt}] {log.content}</code>
              ))}
            </pre>
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
    deviceCache: {},
    logs: {},
    startingInstances: new Set(),
    stoppingInstances: new Set()
  });

  const cacheRef = useRef(new Map());

  const handleLoadDetails = useCallback((uuid) => {
    if (!uuid || cacheRef.current.has(uuid)) return;

    const instance = instances.find(i => i && i.uuid === uuid);
    if (!instance) return;

    cacheRef.current.set(uuid, 'loading');

    const devicePromise = (instance.device && instance.device.id)
      ? Remotelabz.devices.get(instance.device.id).catch(() => ({ data: { name: instance.device?.name || 'Inconnu' } }))
      : Promise.resolve({ data: { name: instance.device?.name || 'Inconnu' } });

    const logsPromise = Remotelabz.instances.device.logs
      ? Remotelabz.instances.device.logs(uuid).catch(() => ({ data: [] }))
      : Promise.resolve({ data: [] });

    Promise.all([devicePromise, logsPromise]).then(([deviceRes, logsRes]) => {
      cacheRef.current.set(uuid, true);
      setSharedStates(prev => ({
        ...prev,
        deviceCache: { ...(prev?.deviceCache || {}), [uuid]: deviceRes.data || { name: instance.device?.name || 'Inconnu' } },
        logs: { ...(prev?.logs || {}), [uuid]: logsRes.data || [] }
      }));
    }).catch(() => {
      // On any unexpected error make sure cache is not left in 'loading' state
      cacheRef.current.delete(uuid);
    });
  }, [instances]);

  const handleStateUpdate = useCallback((action, uuid) => {
    if (action === 'start') {
      setSharedStates(prev => ({
        ...prev,
        startingInstances: new Set([...prev.startingInstances, uuid])
      }));
      onStateUpdateProp(action, uuid);
      setTimeout(() => {
        setSharedStates(prev => ({
          ...prev,
          startingInstances: new Set([...prev.startingInstances].filter(id => id !== uuid))
        }));
      }, 1500);
    } else if (action === 'stop') {
      setSharedStates(prev => ({
        ...prev,
        stoppingInstances: new Set([...prev.stoppingInstances, uuid])
      }));
      onStateUpdateProp(action, uuid);
      setTimeout(() => {
        setSharedStates(prev => ({
          ...prev,
          stoppingInstances: new Set([...prev.stoppingInstances].filter(id => id !== uuid))
        }));
      }, 1500);
    }
  }, [onStateUpdateProp]);

  const openDetailsModal = useCallback((instance) => {
    setSelectedInstance(instance);
  }, []);

  const memoizedInstances = useMemo(() => instances, [instances]);


  if (memoizedInstances.length === 0) {
    return <div className="virtualized-list-empty"><p>Aucune instance disponible</p></div>;
  }

  return (
    <div className="virtualized-list-container">
      <div className="virtualized-list-header">
        <h2>Instances ({memoizedInstances.length})</h2>
      </div>

      <div className="virtualized-list-content">
        <List
          height={Math.max(200, (typeof window !== 'undefined' ? window.innerHeight - 200 : 600))}
          itemCount={memoizedInstances.length}
          itemSize={90}
          width={"100%"}
          itemData={memoizedInstances}
          itemKey={(index, data) => (data?.[index]?.uuid || index)}
        >
          {({ index, style, data }) => (
            <VirtualizedInstanceRow
              index={index}
              style={style}
              data={data}
              onLoadDetails={handleLoadDetails}
              onStateUpdate={handleStateUpdate}
              openDetailsModal={openDetailsModal}
              sharedStates={sharedStates}
            />
          )}
        </List>
      </div>

      <DetailsModal
        selectedInstance={selectedInstance}
        onClose={() => setSelectedInstance(null)}
        logs={sharedStates.logs}
        deviceCache={sharedStates.deviceCache}
      />
    </div>
  );
}