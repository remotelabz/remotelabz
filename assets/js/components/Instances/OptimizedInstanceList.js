import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { List } from 'react-window';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import { ListGroupItem, Button, Spinner, Modal } from 'react-bootstrap';
import { toast } from 'react-toastify';
import { is_vnc, is_login, is_serial, is_real } from './deviceProtocolHelpers';
import InstanceStateBadge from './InstanceStateBadge';
import { fetchDeviceLogs, startLogsPolling, stopLogsPolling, formatLogEntry, getLastLogs } from './deviceLogsHelpers';
import DeviceLogs from './DeviceLogs';

const VirtualizedInstanceRow = React.memo((props) => {
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
          <span>Owner: {labInfo.ownerName || 'N/A'}</span>
          <span className="ml-2">Worker: {labInfo.workerIp || 'N/A'}</span>
        </div>
      </div>

      <div className="instance-actions">
        {(instance.state === 'stopped' || instance.state === 'error') && (
          <button
            className="btn-start"
            onClick={() => onStateUpdate('start', instance.uuid, 'lab')}
            disabled={isStarting}
            title="Start lab"
          >
            {isStarting ? <span className="loading-spinner"></span> : '▶'}
          </button>
        )}
        
        {instance.state === 'started' && (
          <button
            className="btn-stop"
            onClick={() => onStateUpdate('stop', instance.uuid, 'lab')}
            disabled={isStopping}
            title="Stop lab"
          >
            {isStopping ? <span className="loading-spinner"></span> : '■'}
          </button>
        )}

        {(instance.state === 'stopped' || instance.state === 'error') && (
          <button
            className="btn-reset"
            onClick={() => onStateUpdate('reset', instance.uuid, 'lab')}
            disabled={isResetting}
            title="Reset lab"
          >
            {isResetting ? <span className="loading-spinner"></span> : '↻'}
          </button>
        )}

        <button
          className="btn-details"
          onClick={() => openDetailsModal(instance)}
        >
          Details
        </button>
      </div>
    </div>
  );
});

const DetailsModal = React.memo(({ selectedInstance, onClose, sharedStates, onStateUpdate, onRefreshDetails, onLabDeleted }) => {
  if (!selectedInstance) return null;

  const labInfo = sharedStates.labCache[selectedInstance.uuid] || {};
  const deviceInstances = sharedStates.deviceInstancesCache[selectedInstance.uuid] || [];
  
  const [expandedDevice, setExpandedDevice] = useState(null);
  const [deviceStates, setDeviceStates] = useState({});
  const [deviceLogs, setDeviceLogs] = useState({});
  const [expandedLogs, setExpandedLogs] = useState({});
  const logsIntervalsRef = useRef({});
  const [showLeaveLabModal, setShowLeaveLabModal] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [isStopping, setIsStopping] = useState(false);
  const [isStarting, setIsStarting] = useState(false);
  
  // États pour les actions bulk
  const [isBulkStarting, setIsBulkStarting] = useState(false);
  const [isBulkStopping, setIsBulkStopping] = useState(false);
  const [isBulkResetting, setIsBulkResetting] = useState(false);

  const handleDeviceAction = useCallback((deviceUuid, action) => {
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

  // Gestion des actions bulk
  const handleBulkAction = useCallback(async (action) => {
    const setLoading = action === 'start' ? setIsBulkStarting : 
                       action === 'stop' ? setIsBulkStopping : 
                       setIsBulkResetting;
    
    setLoading(true);
    
    try {
      let response;
      switch(action) {
        case 'start':
          response = await Remotelabz.instances.device.startAll(selectedInstance.uuid);
          break;
        case 'stop':
          response = await Remotelabz.instances.device.stopAll(selectedInstance.uuid);
          break;
        case 'reset':
          response = await Remotelabz.instances.device.resetAll(selectedInstance.uuid);
          break;
      }

      const data = response.data;
      
      if (data.success) {
        toast.success(`Successfully ${action}ed ${data[action === 'start' ? 'started' : action === 'stop' ? 'stopped' : 'reset']} out of ${data.total} devices`, {
          autoClose: 5000
        });
      } else {
        toast.warning(`${action} completed with some errors. ${data.errors.length} device(s) failed.`, {
          autoClose: 7000
        });
        
        // Afficher les erreurs individuelles
        data.errors.forEach(error => {
          toast.error(`${error.name}: ${error.error}`, {
            autoClose: 5000
          });
        });
      }
      
      // Rafraîchir les détails
      setTimeout(() => {
        onRefreshDetails(selectedInstance.uuid);
      }, 2000);
      
    } catch (error) {
      console.error(`Error during bulk ${action}:`, error);
      
      const errorMessage = error.response?.data?.message || 
        `An error occurred while trying to ${action} all devices. Please try again.`;
      
      toast.error(errorMessage, {
        autoClose: 10000
      });
    } finally {
      setLoading(false);
    }
  }, [selectedInstance, onRefreshDetails]);

  const handleLeaveLab = useCallback(async () => {
    setShowLeaveLabModal(false);
    setIsDeleting(true);
    
    try {
      await Remotelabz.instances.lab.delete(selectedInstance.uuid);
      
      toast.success('Lab instance deleted successfully', {
        autoClose: 5000
      });
      
      onClose();
      
      if (onLabDeleted) {
        onLabDeleted(selectedInstance.uuid);
      }
      
    } catch (error) {
      console.error('Error deleting lab instance:', error);
      
      const errorMessage = error.response?.data?.message?.includes("Worker") 
        ? error.response.data.message 
        : 'An error happened while leaving the lab. Please try again later.';
      
      toast.error(errorMessage, {
        autoClose: 10000
      });
    } finally {
      setIsDeleting(false);
    }
  }, [selectedInstance, onClose, onLabDeleted]);

  // Gestion des logs
  useEffect(() => {
    deviceInstances.forEach(device => {
      if (logsIntervalsRef.current[device.uuid]) {
        stopLogsPolling(logsIntervalsRef.current[device.uuid]);
      }

      if (device.state !== 'stopped') {
        logsIntervalsRef.current[device.uuid] = startLogsPolling(
          device.uuid,
          (logs) => {
            setDeviceLogs(prev => ({
              ...prev,
              [device.uuid]: logs
            }));
          },
          30000
        );
      }
    });

    return () => {
      Object.values(logsIntervalsRef.current).forEach(intervalId => {
        stopLogsPolling(intervalId);
      });
      logsIntervalsRef.current = {};
    };
  }, [deviceInstances]);

  const refreshTimerRef = useRef(null);

  useEffect(() => {
    if (!selectedInstance) return;

    const refreshDetails = () => {
      onRefreshDetails(selectedInstance.uuid);
    };

    refreshTimerRef.current = setInterval(refreshDetails, 5000);

    return () => {
      if (refreshTimerRef.current) {
        clearInterval(refreshTimerRef.current);
      }
    };
  }, [selectedInstance, onRefreshDetails]);

  const toggleLogs = (deviceUuid) => {
    setExpandedLogs(prev => ({
      ...prev,
      [deviceUuid]: !prev[deviceUuid]
    }));
  };

  return (<>
    <div className="modal fade show" tabIndex="-1" role="dialog" style={{ display: 'block', backgroundColor: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-lg" role="document">
        <div className="modal-content">
          {/* Header */}
          <div className="modal-header border-bottom">
            <div style={{ width: '100%' }}>
              {/* First row: Instance info and close button */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                <div>
                  <h4 style={{ marginBottom: 0 }}>
                    {labInfo.name || 'Lab'}
                    &nbsp;
                    <span className={`badge badge-${selectedInstance.state === 'created' ? 'success' : selectedInstance.state === 'creating' ? 'warning' : 'secondary'}`}>
                      {selectedInstance.state}
                    </span>
                  </h4>
                  <small style={{ color: '#6c757d' }}>Instance: {selectedInstance.uuid}</small>
                </div>
                
                <button 
                  type="button" 
                  className="close" 
                  onClick={onClose}
                  style={{ position: 'absolute', right: '1rem', top: '1rem' }}
                >
                  <span>&times;</span>
                </button>
              </div>

              {/* Bulk device action buttons */}
              {deviceInstances.length > 0 && (
                <div style={{ borderTop: '1px solid #dee2e6', paddingTop: '12px' }}>
                  <small style={{ color: '#6c757d', display: 'block', marginBottom: '8px' }}>
                    Apply to all devices:
                  </small>
                  <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                    <button
                      className="btn btn-sm btn-success"
                      onClick={() => handleBulkAction('start')}
                      disabled={isBulkStarting}
                      title="Start all devices"
                      style={{ fontSize: '11px', padding: '4px 8px' }}
                    >
                      {isBulkStarting ? (
                        <Spinner animation="border" size="sm" />
                      ) : (
                        <><SVG name="play" /> Start All</>
                      )}
                    </button>
                    
                    <button
                      className="btn btn-sm btn-danger"
                      onClick={() => handleBulkAction('stop')}
                      disabled={isBulkStopping}
                      title="Stop all devices"
                      style={{ fontSize: '11px', padding: '4px 8px' }}
                    >
                      {isBulkStopping ? (
                        <Spinner animation="border" size="sm" />
                      ) : (
                        <><SVG name="stop" /> Stop All</>
                      )}
                    </button>
                    
                    <button
                      className="btn btn-sm btn-warning"
                      onClick={() => handleBulkAction('reset')}
                      disabled={isBulkResetting}
                      title="Reset all devices"
                      style={{ fontSize: '11px', padding: '4px 8px' }}
                    >
                      {isBulkResetting ? (
                        <Spinner animation="border" size="sm" />
                      ) : (
                        <><SVG name="redo" /> Reset All</>
                      )}
                    </button>
                    
                    <button 
                      className="btn btn-sm btn-danger" 
                      onClick={() => setShowLeaveLabModal(true)}
                      disabled={isDeleting}
                      style={{ marginLeft: 'auto' }}
                    >
                      {isDeleting ? <Spinner animation="border" size="sm" /> : 'Leave lab'}
                    </button>
                  </div>
                </div>
              )}
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
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                          {/* Left side: Device name and state */}
                          <div style={{ flex: 1 }}>
                            <div style={{ display: 'flex', alignItems: 'center', marginBottom: '8px' }}>
                              <h6 style={{ marginBottom: 0, marginRight: '8px' }}>
                                {deviceInstance.device?.name || 'Unknown device'}
                              </h6>
                              <InstanceStateBadge state={deviceInstance.state}/>
                            </div>
                            <small style={{ color: '#6c757d', display: 'block' }}>
                              {deviceInstance.uuid}
                            </small>
                          </div>

                          {/* Right side: Action buttons */}
                          <div style={{ display: 'flex', gap: '8px', marginLeft: '12px', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                            {deviceInstance.state !== 'stopped' && (
                              <button
                                className="btn btn-sm btn-info"
                                onClick={() => toggleLogs(deviceInstance.uuid)}
                                title={expandedLogs[deviceInstance.uuid] ? 'Hide logs' : 'Show logs'}
                              >
                                <SVG name={expandedLogs[deviceInstance.uuid] ? 'chevron-down' : 'chevron-right'} />
                              </button>
                            )}
                            {(deviceInstance.state === 'stopped' || deviceInstance.state === 'error' || deviceInstance.state === 'reset') && (
                              <button
                                className="btn btn-sm btn-success"
                                onClick={() => handleDeviceAction(deviceInstance.uuid, 'start')}
                                disabled={deviceStates[deviceInstance.uuid] === 'start'}
                                title="Start"
                              >
                                {deviceStates[deviceInstance.uuid] === 'start' ? <Spinner animation="border" size="sm" /> : <SVG name="play" />}
                              </button>
                            )}
                            {(deviceInstance.state === 'started' && is_login(deviceInstance)) &&
                              <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href={"/instances/" + deviceInstance.uuid + "/view/login"}
                                className="btn btn-sm btn-primary"
                                title="Open Login console"
                              >
                                <SVG name="terminal" />
                              </a>
                            }
                            {(deviceInstance.state === 'started' && is_vnc(deviceInstance)) &&
                              <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href={"/instances/" + deviceInstance.uuid + "/view/vnc"}
                                className="btn btn-sm btn-primary"
                                title="Open VNC console"
                              >
                                <SVG name="external-link" />
                              </a>
                            }
                            {deviceInstance.state === 'started' && (
                              <button
                                className="btn btn-sm btn-danger"
                                onClick={() => handleDeviceAction(deviceInstance.uuid, 'stop')}
                                disabled={deviceStates[deviceInstance.uuid] === 'stop'}
                                title="Stop"
                              >
                                {deviceStates[deviceInstance.uuid] === 'stop' ? <Spinner animation="border" size="sm" /> : <SVG name="stop" />}
                              </button>
                            )}
                            {(deviceInstance.state === 'stopped' || deviceInstance.state === 'error') && (
                              <button
                                className="btn btn-sm btn-warning"
                                onClick={() => handleDeviceAction(deviceInstance.uuid, 'reset')}
                                disabled={deviceStates[deviceInstance.uuid] === 'reset'}
                                title="Reset"
                              >
                                {deviceStates[deviceInstance.uuid] === 'reset' ? <Spinner animation="border" size="sm" /> : <SVG name="redo" />}
                              </button>
                            )}
                          </div>
                        </div>

                        {/* Logs section */}
                        {deviceInstance.state !== 'stopped' && (
                          <DeviceLogs 
                            logs={deviceLogs[deviceInstance.uuid] || []} 
                            showLogs={expandedLogs[deviceInstance.uuid] || false} 
                            maxLogs={50}
                            searchable={true}
                          />
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {deviceInstances.length === 0 && (
                <div className="alert alert-info">
                  <p className="mb-0">No device in this instance</p>
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
    
    {/* Leave Lab Confirmation Modal */}
    <Modal show={showLeaveLabModal} onHide={() => setShowLeaveLabModal(false)}>
      <Modal.Header closeButton>
        <Modal.Title>Leave lab</Modal.Title>
      </Modal.Header>
      <Modal.Body>
        If you leave the lab, <strong>all your instances will be deleted and all virtual machines associated will be destroyed.</strong> Are you sure you want to leave this lab?
      </Modal.Body>
      <Modal.Footer>
        <Button variant="default" onClick={() => setShowLeaveLabModal(false)}>Close</Button>
        <Button variant="danger" onClick={handleLeaveLab} disabled={isDeleting}>
          {isDeleting ? <><Spinner animation="border" size="sm" /> Leaving...</> : 'Leave'}
        </Button>
      </Modal.Footer>
    </Modal>
  </>);
});

export default function OptimizedInstanceList({ 
  instances = [], 
  user = {},
  onStateUpdate: onStateUpdateProp = () => {},
  onLabDeleted = () => {}
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
    const currentUuids = new Set(instances.map(i => i.uuid));
    
    const newCache = new Map();
    currentUuids.forEach(uuid => {
      if (cacheRef.current.has(uuid)) {
        newCache.set(uuid, true);
      }
    });
    cacheRef.current = newCache;
    
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
      return;
    }

    const instance = instances.find(i => i.uuid === uuid);
    if (!instance) {
      return;
    }

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
    setSelectedInstance(instance);
  }, []);

  const memoizedInstances = useMemo(() => instances, [instances]);

  const handleRefreshDetails = useCallback((uuid) => {
    Remotelabz.instances.lab.get(uuid).then((updatedInstance) => {
      const labInfo = updatedInstance.data.lab || {};
      const ownerInfo = updatedInstance.data.owner || {};
      
      setSharedStates(prev => ({
        ...prev,
        labCache: { 
          ...prev.labCache, 
          [uuid]: {
            name: labInfo.name || 'Lab inconnu',
            ownerName: ownerInfo.name || 'N/A',
            workerIp: updatedInstance.data.workerIp || 'N/A',
            network: updatedInstance.data.network?.ip?.addr || 'N/A',
            state: updatedInstance.data.state
          }
        },
        deviceInstancesCache: {
          ...prev.deviceInstancesCache,
          [uuid]: Array.isArray(updatedInstance.data.deviceInstances) ? updatedInstance.data.deviceInstances : []
        }
      }));
    }).catch(err => {
      console.error(`Erreur lors du rafraîchissement de ${uuid}:`, err);
    });
  }, []);

  if (!Array.isArray(memoizedInstances) || memoizedInstances.length === 0) {
    return <div className="virtualized-list-empty"><p>Aucune instance disponible</p></div>;
  }

  return (<>
    <div className="virtualized-list-container">
      <div className="virtualized-list-header">
        <h2>Instances ({memoizedInstances.length})</h2>
      </div>

      <div className="virtualized-list-content">
        <List
          rowComponent={VirtualizedInstanceRow}
          rowCount={memoizedInstances.length}
          rowHeight={75}
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
        onRefreshDetails={handleRefreshDetails}
        onLabDeleted={onLabDeleted}
      />
    </div>
  </>);
}