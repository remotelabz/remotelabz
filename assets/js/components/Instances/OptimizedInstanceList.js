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
  
  console.log("instance ligne 22",instance);
  useEffect(() => {
    onLoadDetails(instance.uuid);
  }, [instance.uuid, onLoadDetails]);

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
    if (cacheRef.current.has(uuid)) return;

    cacheRef.current.set(uuid, 'loading');

    const instance = instances.find(i => i.uuid === uuid);
    console.log("instance line 154", instance.deviceInstances[0]?.device);
    if (!instance) return;

    Promise.all([
      Remotelabz.devices.get(instance.deviceInstances[0]?.device?.id).catch(() => ({ data: { name: instance.deviceInstances[0]?.device?.name } })),
      Remotelabz.instances.device.logs(uuid).catch(() => ({ data: [] }))
    ]).then(([deviceRes, logsRes]) => {
      cacheRef.current.set(uuid, true);
      setSharedStates(prev => ({
        ...prev,
        deviceCache: { ...prev.deviceCache, [uuid]: deviceRes.data || { name: instance.deviceInstances[0]?.device?.name  } },
        logs: { ...prev.logs, [uuid]: logsRes.data || [] }
      }));
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

  console.log("memoizedInstances line 203 ",memoizedInstances)
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
        logs={sharedStates.logs}
        deviceCache={sharedStates.deviceCache}
      />
    </div>
  );
}