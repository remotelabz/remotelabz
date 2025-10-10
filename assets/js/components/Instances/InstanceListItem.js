import { ToastContainer, toast } from 'react-toastify';
import API from '../../api';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import Routing from 'fos-jsrouting';
import React, { useState, useEffect, Component } from 'react';
import InstanceStateBadge from './InstanceStateBadge';
import InstanceExport from './InstanceExport';
import { ListGroupItem, Button, Spinner, Modal } from 'react-bootstrap';

const api = API.getInstance();

function InstanceListItem({ instance, labDeviceLength, allInstance,  showControls, onStateUpdate, isSandbox, lab, user, allInstancesPage }) {
    const [isLoading, setLoading] = useState(true)
    const [isExporting, setExporting] = useState(false)
    const [logs, setLogs] = useState([])
    const [showLogs, setShowLogs] = useState(false)
    const [showExport, setShowExport] = useState(false)
    const [device, setDevice] = useState({ name: '' });
    const [showResetDeviceModel, setShowResetDeviceModel] = useState(false)
    const [showStopDeviceModel, setShowStopDeviceModel] = useState(false)
    
    // États séparés pour chaque action
    const [startingInstances, setStartingInstances] = useState(new Set());
    const [stoppingInstances, setStoppingInstances] = useState(new Set());
    const [resettingInstances, setResettingInstances] = useState(new Set());

    // Fonctions pour gérer l'état "starting"
    const setInstanceStarting = (uuid, starting) => {
        setStartingInstances(prev => {
            const newSet = new Set(prev);
            if (starting) {
                newSet.add(uuid);
            } else {
                newSet.delete(uuid);
            }
            return newSet;
        });
    };

    const isInstanceStarting = (uuid) => {
        return startingInstances.has(uuid);
    };

    // Fonctions pour gérer l'état "stopping"
    const setInstanceStopping = (uuid, stopping) => {
        setStoppingInstances(prev => {
            const newSet = new Set(prev);
            if (stopping) {
                newSet.add(uuid);
            } else {
                newSet.delete(uuid);
            }
            return newSet;
        });
    };

    const isInstanceStopping = (uuid) => {
        return stoppingInstances.has(uuid);
    };

    // Fonctions pour gérer l'état "resetting"
    const setInstanceResetting = (uuid, resetting) => {
        setResettingInstances(prev => {
            const newSet = new Set(prev);
            if (resetting) {
                newSet.add(uuid);
            } else {
                newSet.delete(uuid);
            }
            return newSet;
        });
    };

    const isInstanceResetting = (uuid) => {
        return resettingInstances.has(uuid);
    };

    

    //console.log("Nombre total d'instances :", allInstance?.length);
    useEffect(() => {
        fetchLogs()
        //Collect log every 30 seconds
        const interval = setInterval(fetchLogs, 30000)
        Remotelabz.devices.get(instance.device.id).then(response => {
            setDevice(response.data)
            setLoading(false) // Use to load all devices in lab
        })
        return () => {
            clearInterval(interval)
        }
    }, [instance])

    useEffect(() => {
        // Nettoyer les états de loading quand l'instance change d'état
        if (
            instance.state === 'started' ||
            instance.state === 'stopped' ||
            instance.state === 'error' ||
            instance.state === 'exported' ||
            instance.state === 'reset'
        ) {
            setInstanceStarting(instance.uuid, false);
            setInstanceStopping(instance.uuid, false);
            setInstanceResetting(instance.uuid, false);
        }
    }, [instance.state]);

    function fetchLogs() {
        return Remotelabz.instances.device.logs(instance.uuid).then(response => {
            setLogs(response.data);
        }).catch(error => {
            if (error.response.status === 404) {
                setLogs([]);
            } else {
                toast.error('An error happened while fetching instance logs. If this error persist, please contact an administrator.', {
                });
                setDevice(null)
            }
        });
    }

    function startDevice(deviceInstance) {
        setInstanceStarting(deviceInstance.uuid, true);
        Remotelabz.instances.device.start(deviceInstance.uuid).then(() => {
            toast.success('Instance start requested.');
            onStateUpdate();
        }).catch((error) => {
            setInstanceStarting(deviceInstance.uuid, false);
            const errorMessage = error?.response?.data?.message || '';
            if (errorMessage.includes("Worker") && errorMessage.includes("is suspended")) {
                toast.error(errorMessage, { autoClose: 10000 });
            } else if (errorMessage.includes("Device")) {
                toast.error(errorMessage, { autoClose: 5000 });
            } else {
                toast.error("Error while requesting instance start. Please try again later.");
            }
        });
    }

    function stopDevice(deviceInstance) {
        setInstanceStopping(deviceInstance.uuid, true);
        if (showStopDeviceModel) {
            setShowStopDeviceModel(false);
        }
        Remotelabz.instances.device.stop(deviceInstance.uuid).then(() => {
            toast.success('Instance stop requested.');
            onStateUpdate();
        }).catch((error) => {
            setInstanceStopping(deviceInstance.uuid, false);
            const errorMessage = error?.response?.data?.message || '';
            if (errorMessage.includes("Worker") && errorMessage.includes("is suspended")) {
                toast.error(errorMessage, { autoClose: 10000 });
            } else if (errorMessage.includes("Device")) {
                toast.error(errorMessage, { autoClose: 5000 });
            } else {
                toast.error("Error while requesting instance stop. Please try again later.");
            }
        });
    }

    function resetDevice(deviceInstance) {
        setShowResetDeviceModel(false);
        setInstanceResetting(deviceInstance.uuid, true);
        
        Remotelabz.instances.device.reset(deviceInstance.uuid).then(() => {
            toast.success('Instance reset requested.');
            onStateUpdate();
        }).catch((error) => {
            setInstanceResetting(deviceInstance.uuid, false);
            const errorMessage = error?.response?.data?.message || '';
            if (errorMessage.includes("Worker") && errorMessage.includes("is suspended")) {
                toast.error(errorMessage, { autoClose: 10000 });
            } else if (errorMessage.includes("Device")) {
                toast.error(errorMessage, { autoClose: 10000 });
            } else {
                toast.error("Error while requesting instance reset. Please try again later.", { autoClose: 10000 });
            }
        });
    }

    // Fonctions pour vérifier si une action spécifique est en cours
    function isStarting(deviceInstance) {
        return deviceInstance.state === 'starting' || isInstanceStarting(deviceInstance.uuid);
    }

    function isStopping(deviceInstance) {
        return deviceInstance.state === 'stopping' || isInstanceStopping(deviceInstance.uuid);
    }

    function isResetting(deviceInstance) {
        return deviceInstance.state === 'resetting' || isInstanceResetting(deviceInstance.uuid);
    }

    // Fonction générale pour vérifier si l'instance est dans un état de calcul
    function isComputingState(deviceInstance) {
        return isStarting(deviceInstance) || isStopping(deviceInstance) || isResetting(deviceInstance);
    }

    function exportDeviceTemplate(deviceInstance, name) {
        setExporting(true)
        
        Remotelabz.instances.export(deviceInstance.uuid, name, "device").then((response) => {
            //console.log("response export device:", response);
            toast.success('Instance export requested.', {
                });

            onStateUpdate();
        }).catch((error) => {
            if (error.response.data.message.includes("No worker available")) {
                
                toast.error(error.response.data.message, {
                    autoClose: 10000
                });
            }
            else {
                 toast.error('Error while requesting instance export. Please try again later.', {
                    autoClose: 10000
                });
            }

            setExporting(false)
        })
    }
    
    function is_vnc() {
        let result=false;
        if (instance.controlProtocolTypeInstances.length > 0 ) {
            instance.controlProtocolTypeInstances.forEach((element,index) => {
              if (element.controlProtocolType.name === 'vnc') {
                result=(result || true);
              }
            });
        }
        return result;
    }

    function is_login() {
        let result=false;
        if (instance.controlProtocolTypeInstances.length > 0 ) {
            instance.controlProtocolTypeInstances.forEach((element,index) => {
              if (element.controlProtocolType.name === 'login') {
                result=(result || true);
              }
            });
        }
        return result;
    }

    function is_serial() {
        let result=false;
        if (instance.controlProtocolTypeInstances.length > 0 ) {
            instance.controlProtocolTypeInstances.forEach((element,index) => {
              if (element.controlProtocolType.name === 'serial') {
                result=(result || true);
              }
            });
        }
        return result;
    }

    function is_real() {
        let result=false;
        if (instance.device.hypervisor.name == "physical" ) {
            instance.controlProtocolTypeInstances.forEach((element,index) => {
              result = true;
            })
        }
        return result;
    }
    
    let controls;
    
    switch (instance.state) {
        case 'error':
            controls = (
                <Button 
                    className="ml-3" 
                    variant="success" 
                    title="Start device" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    onClick={() => startDevice(instance)} 
                    disabled={isComputingState(instance)}
                >
                    {isStarting(instance) ? <Spinner animation="border" size="sm" /> : <SVG name="play" />}
                </Button>
            );
            break;

        case 'stopped':
            controls = (
                <Button 
                    className="ml-3" 
                    variant="success" 
                    title="Start device" 
                    onClick={() => {
                        //console.log('Button clicked, isComputing before:', isComputing || isInstanceComputing(instance.uuid));
                        startDevice(instance);
                    }}
                    disabled={isComputingState(instance)}
                >
                    {isStarting(instance) ? <Spinner animation="border" size="sm" /> : <SVG name="play" />}
                </Button>
            );

            break;

        case 'reset':
            controls = (
                <Button 
                    className="ml-3" 
                    variant="success" 
                    title="Start device" 
                    onClick={() => {
                        //console.log('Button clicked, isComputing before:', isComputing || isInstanceComputing(instance.uuid));
                        startDevice(instance);
                    }}
                    disabled={isComputingState(instance)}
                >
                    {isStarting(instance) ? <Spinner animation="border" size="sm" /> : <SVG name="play" />}
                </Button>
            );
            break;

        case 'starting':
            controls = (<Button className="ml-3" variant="dark" title="Start device" data-toggle="tooltip" data-placement="top" disabled>
                <Spinner animation="border" size="sm" />
            </Button>);
            break;

        case 'stopping':
            controls = (<Button className="ml-3" variant="dark" title="Stop device" data-toggle="tooltip" data-placement="top" disabled>
                <Spinner animation="border" size="sm" />
            </Button>);
            break;

        case 'exporting':
            controls = (<Button className="ml-3" variant="dark" title="Export device" data-toggle="tooltip" data-placement="top" disabled>
                <Spinner animation="border" size="sm" />
            </Button>);
            break;
        
        case 'resetting':
            controls = (<Button className="ml-3" variant="dark" title="Reset device" data-toggle="tooltip" data-placement="top" disabled>
                <Spinner animation="border" size="sm" />
            </Button>);
            break;

        case 'started':
            controls = (
                <Button 
                    className="ml-3" 
                    variant="danger" 
                    title="Stop device" 
                    onClick={() => {
                        stopDevice(instance);
                    }}
                    disabled={isComputingState(instance)}
                >
                    {isStopping(instance) ? <Spinner animation="border" size="sm" /> : <SVG name="stop" />}
                </Button>
            );
            break;
    }
    return (
        <>
         <ListGroupItem>
            {isLoading ?
                <div className="d-flex align-items-center">
                    <div className="m-3">
                        <div className="dot-bricks"></div>
                    </div>
                    <div className="ml-2">
                        Loading...
                    </div>
                </div>
            :
            <div>
                <div className="d-flex justify-content-between">
                    <div className="d-flex flex-column">
                        {instance.device.type != "switch" ?
                        <div>
                            {device.name} <InstanceStateBadge state={instance.state} className="ml-1" />
                        </div> :
                        <div>
                            {device.name} <InstanceStateBadge state={"started"} className="ml-1" />
                        </div>
                        }
                        
                        <div className="text-muted small">
                            {instance.uuid}
                        </div>
                    </div>

                    <div className="d-flex align-items-center">
                        {( (instance.state == 'stopped' || instance.state == 'exported') && (allInstance?.length == labDeviceLength) && isSandbox && instance.device.name !== "DHCP_service") &&
                            <div onClick={() => setShowExport(!showExport)}>
                                <Button variant="default">
                                    <SVG name={showExport ? "chevron-down" : "chevron-right"} />
                                        Export device
                                </Button>
                            </div>
                        }
                        {instance.state !== 'stopped' && 
                            <div onClick={() => setShowLogs(!showLogs)}>
                                {showLogs ?
                                    <Button variant="default"><SVG name="chevron-down"></SVG> Hide logs</Button>
                                :
                                    <Button variant="default"><SVG name="chevron-right"></SVG> Show logs</Button>
                                }
                            </div>
                        }

                        { 
                        (instance.ownedBy != 'group' 
                        && (instance.state === 'stopped' || instance.state === 'error') 
                        && instance.device.type != 'switch' 
                        && !isSandbox 
                        && user.roles 
                        && (user.roles.includes("ROLE_ADMINISTRATOR") || 
                            user.roles.includes("ROLE_SUPER_ADMINISTRATOR") || 
                            user.roles.includes("ROLE_USER") ||
                            user.roles.includes("ROLE_TEACHER") || 
                            user.roles.includes("ROLE_TEACHER_EDITOR") )
                        )
                        &&
                            <Button 
                                className="ml-3" 
                                variant="danger" 
                                title="Reset device" 
                                onClick={() => {
                                    setShowResetDeviceModel(true);
                                }}
                                disabled={isComputingState(instance)}
                            >
                                {isResetting(instance) ? <Spinner animation="border" size="sm" /> : <SVG name="redo" />}
                            </Button>
                        }        
                        {instance.ownedBy == "group" && showControls && (instance.state === 'stopped' || instance.state === 'error') && instance.device.type != 'switch' &&
                            <Button 
                                variant="danger" 
                                title="Reset device" 
                                data-toggle="tooltip" 
                                data-placement="top" 
                                className="ml-3" 
                                onClick={() => setShowResetDeviceModel(true)}
                                disabled={isComputingState(instance)}
                            >
                                {isResetting(instance) ? <Spinner animation="border" size="sm" /> : <SVG name="redo" />}
                            </Button>
                        }
                        {(instance.state == 'started' && (instance.controlProtocolTypeInstances.length>0
                         && is_login()) && !is_real() && !isSandbox && user.roles &&(user.roles.includes("ROLE_ADMINISTRATOR") || user.roles.includes("ROLE_SUPER_ADMINISTRATOR") || ((user.roles.includes("ROLE_TEACHER") || user.roles.includes("ROLE_TEACHER_EDITOR")) 
                         //&& user.id === lab.author.id
                        ))
                         )
                         &&
                            <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href={"/instances/" + instance.uuid + "/view/admin"}
                                className="btn btn-primary ml-3"
                                title="Open VNC console"
                                data-toggle="tooltip"
                                data-placement="top"
                            >
                                <SVG name="incognito" />
                            </a>
                        }

                        {(instance.state == 'started' && (instance.controlProtocolTypeInstances.length>0
                         && is_vnc())
                         )
                         &&
                            <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href={"/instances/" + instance.uuid + "/view/vnc"}
                                className="btn btn-primary ml-3"
                                title="Open VNC console"
                                data-toggle="tooltip"
                                data-placement="top"
                            >
                                <SVG name="external-link" />
                            </a>
                        }
                        {(instance.state == 'started' && (instance.controlProtocolTypeInstances.length>0
                         && is_login())
                         )
                         &&
                            <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href={"/instances/" + instance.uuid + "/view/login"}
                                className="btn btn-primary ml-3"
                                title="Open Login console"
                                data-toggle="tooltip"
                                data-placement="top"
                            >
                                <SVG name="terminal" />
                            </a>
                        }
                        {(instance.state == 'started' && (instance.controlProtocolTypeInstances.length>0
                         && is_serial())
                         )
                         &&
                            <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href={"/instances/" + instance.uuid + "/view/serial"}
                                className="btn btn-primary ml-3"
                                title="Open Serial console"
                                data-toggle="tooltip"
                                data-placement="top"
                            >
                                <SVG name="admin" />
                            </a>
                        }

                        {instance.device.type != "switch" && showControls && 
                            controls
                        }
                        {instance.ownedBy != 'group' && allInstancesPage && (instance.state != 'stopped' && instance.state != 'error' && instance.state != 'exported' && instance.state != 'reset' && instance.state != 'started') && instance.device.type != 'switch' && !isSandbox && user.roles &&
                            (user.roles.includes("ROLE_ADMINISTRATOR") || user.roles.includes("ROLE_SUPER_ADMINISTRATOR") || (user.roles.includes("ROLE_TEACHER") || user.roles.includes("ROLE_TEACHER_EDITOR"))) &&
                            <Button 
                                variant="danger" 
                                className="ml-3" 
                                onClick={() => setShowStopDeviceModel(true)}
                                disabled={isComputingState(instance)}
                            >
                                {isStopping(instance) ? <Spinner animation="border" size="sm" /> : <SVG name="stop" />}
                            </Button>
                        }
                        {instance.ownedBy == "group" && showControls && allInstancesPage && (instance.state != 'stopped' && instance.state != 'error' && instance.state != 'exported' && instance.state != 'reset' && instance.state != 'started') && instance.device.type != 'switch' &&
                            <Button 
                                variant="danger" 
                                className="ml-3" 
                                onClick={() => setShowStopDeviceModel(true)}
                                disabled={isComputingState(instance)}
                            >
                                {isStopping(instance) ? <Spinner animation="border" size="sm" /> : <SVG name="stop" />}
                            </Button>
                        }
                    </div>
                </div>
                {(instance.state !== 'stopped' && showLogs) && 
                    <pre className="d-flex flex-column mt-2">
                        {(instance.state != 'stopped' && logs) && logs.map((log, index) => {
                            return <code className="p-1" key={log.id}>[{log.createdAt}] {log.content}</code>;
                        })}
                    </pre>
                }

                {(instance.state == 'stopped' && showExport) &&
                    <InstanceExport instance={instance} exportTemplate={exportDeviceTemplate} type="device"></InstanceExport>
                }

            </div>
            }
        </ListGroupItem>
        <Modal show={showResetDeviceModel} onHide={() => setShowResetDeviceModel(false)}>
            <Modal.Header closeButton>
                <Modal.Title>Reset device</Modal.Title>
            </Modal.Header>
            <Modal.Body>
                If you reset the device, data will be lost. Do you still want to continue?
            </Modal.Body>
            <Modal.Footer>
                <Button variant="default" onClick={() => setShowResetDeviceModel(false)}>Close</Button>
                <Button 
                    variant="danger" 
                    onClick={() => resetDevice(instance)}
                    disabled={isResetting(instance)}
                >
                    {isResetting(instance) ? <Spinner animation="border" size="sm" /> : "Reset"}
                </Button>
            </Modal.Footer>
        </Modal>
        <Modal show={showStopDeviceModel} onHide={() => setShowStopDeviceModel(false)}>
            <Modal.Header closeButton>
                <Modal.Title>Force to stop device</Modal.Title>
            </Modal.Header>
            <Modal.Body>
                Are you sure you want to force to stop the device?
            </Modal.Body>
            <Modal.Footer>
                <Button variant="default" onClick={() => setShowStopDeviceModel(false)}>Close</Button>
                <Button 
                    variant="danger" 
                    onClick={() => stopDevice(instance)}
                    disabled={isStopping(instance)}
                >
                    {isStopping(instance) ? <Spinner animation="border" size="sm" /> : "Stop"}
                </Button>
            </Modal.Footer>
        </Modal>
        </>
    )
}

export default InstanceListItem;