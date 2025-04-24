import Noty from 'noty';
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
    const [isComputing, setComputing] = useState(false)
    const [isExporting, setExporting] = useState(false)
    const [logs, setLogs] = useState([])
    const [showLogs, setShowLogs] = useState(false)
    const [showExport, setShowExport] = useState(false)
    const [device, setDevice] = useState({ name: '' });
    const [showResetDeviceModel, setShowResetDeviceModel] = useState(false)
    const [showStopDeviceModel, setShowStopDeviceModel] = useState(false)
    
    console.log("Nombre total d'instances :", allInstance?.length);
    useEffect(() => {
        fetchLogs()
        //Collect log every 30 seconds
        const interval = setInterval(fetchLogs, 30000)
        Remotelabz.devices.get(instance.device.id).then(response => {
            setDevice(response.data)
            setLoading(false)
        })
        return () => {
            clearInterval(interval)
        }
    }, [instance])

    function fetchLogs() {
        return Remotelabz.instances.device.logs(instance.uuid).then(response => {
            setLogs(response.data);
        }).catch(error => {
            if (error.response.status === 404) {
                setLogs([]);
            } else {
                new Noty({
                    text: 'An error happened while fetching instance logs. If this error persist, please contact an administrator.',
                    type: 'error'
                }).show();
                setDevice(null)
            }
        });
    }

    function startDevice(deviceInstance) {
        setComputing(true)

        Remotelabz.instances.device.start(deviceInstance.uuid).then(() => {
            new Noty({
                type: 'success',
                text: 'Instance start requested.',
                timeout: 10000
            }).show();

            onStateUpdate();
        }).catch((error) => {
            if (error.response.data.message.includes("Worker") && error.response.data.message.includes("is suspended")) {
                new Noty({
                    text: error.response.data.message,
                    type: 'error',
                    timeout: 5000
                }).show()
            } else if (error.response.data.message.includes("Device")) {
                new Noty({
                    text: error.response.data.message,
                    type: 'error',
                    timeout: 5000
                }).show()
            }
            else {
                new Noty({
                    type: 'error',
//                    text: error.response.data.message,
                    text: 'Error while requesting instance start. Please try again later.',
                    timeout: 5000
                }).show();
            }
            setComputing(false)
        })
    }

    function stopDevice(deviceInstance) {
        setComputing(true)
        if (showStopDeviceModel == true) {
            setShowStopDeviceModel(false);
        }

        Remotelabz.instances.device.stop(deviceInstance.uuid).then(() => {
            new Noty({
                type: 'success',
                text: 'Instance stop requested.',
                timeout: 10000
            }).show();

            onStateUpdate();
        }).catch((error) => {
            if (error.response.data.message.includes("Worker") && error.response.data.message.includes("is suspended")) {
                new Noty({
                    text: error.response.data.message,
                    type: 'error'
                }).show()
            }
            else {
                new Noty({
                    type: 'error',
                    text: 'Error while requesting instance stop. Please try again later.',
                    timeout: 5000
                }).show();
            }
            setComputing(false)
        })
    }

    function resetDevice(deviceInstance) {
        setShowResetDeviceModel(false)
        setComputing(true);

        Remotelabz.instances.device.reset(deviceInstance.uuid).then(() => {
            new Noty({
                type: 'success',
                text: 'Device reset requested.',
                timeout: 5000
            }).show();

            onStateUpdate();
        }).catch((error) => {
            if (error.response.data.message.includes("Worker") && error.response.data.message.includes("is suspended")) {
                new Noty({
                    text: error.response.data.message,
                    type: 'error'
                }).show()
            }
            else {
                new Noty({
                    type: 'error',
                    text: 'Error while requesting instance reset. Please try again later.',
                    timeout: 5000
                }).show();
            }
            setComputing(false)
        })
    }

    function isComputingState(deviceInstance) {
        return deviceInstance.state === 'starting' || deviceInstance.state === 'stopping';
    }

    function exportDeviceTemplate(deviceInstance, name) {
        setExporting(true)
        
        Remotelabz.instances.export(deviceInstance.uuid, name, "device").then(() => {
            new Noty({
                type: 'success',
                text: 'Instance export requested.',
                timeout: 5000
            }).show();

            onStateUpdate();
        }).catch((error) => {
            if (error.response.data.message.includes("No worker available")) {
                new Noty({
                    text: error.response.data.message,
                    type: 'error'
                }).show()
            }
            else {
                new Noty({
                    type: 'error',
                    text: 'Error while requesting instance export. Please try again later.',
                    timeout: 5000
                }).show();
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
    console.log('instance',instance);
    console.log('user',user);
    console.log('lab',lab);

    switch (instance.state) {
        case 'error':
            controls = (<Button className="ml-3" variant="success" title="Start device" data-toggle="tooltip" data-placement="top" onClick={() => startDevice(instance)} disabled={isComputingState(instance)}>
                <SVG name="play" />
            </Button>);
            break;

        case 'stopped':
            controls = (<Button className="ml-3" variant="success" title="Start device" data-toggle="tooltip" data-placement="top" onClick={() => startDevice(instance)} disabled={isComputingState(instance)}>
                <SVG name="play" />
            </Button>);
		console.log("test stopped");
            break;

        case 'reset':
            controls = (<Button className="ml-3" variant="success" title="Start device" data-toggle="tooltip" data-placement="top" onClick={() => startDevice(instance)} disabled={isComputingState(instance)}>
                <SVG name="play" />
            </Button>);
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
                    data-toggle="tooltip"
                    data-placement="top"
                    onClick={() => stopDevice(instance)}
                    disabled={isComputingState(instance)}
                >
                    <SVG name="stop" />
                </Button>
            );
            
            break;
    }
console.log("test 2");
    return (
        <><ListGroupItem>
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
{console.log("test3")}
{console.log("is sandbox", isSandbox)}
{console.log("device length", instance.length)}
{console.log("labdevicelength", labDeviceLength)}
                    <div className="d-flex align-items-center">
                        {( (instance.state == 'stopped' || instance.state == 'exported') && (allInstance?.length == labDeviceLength) && isSandbox) &&
                            <div onClick={() => setShowExport(!showExport)}>
                                {showExport ?
                                    <Button variant="default"><SVG name="chevron-down"></SVG> Export</Button>
                                    :
                                    <Button variant="default"><SVG name="chevron-right"></SVG> Export</Button>
                                }
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
                            <Button variant="danger" title="Reset device" data-toggle="tooltip" data-placement="top" className="ml-3" onClick={() => setShowResetDeviceModel(true)}><SVG name="redo"></SVG></Button>
                    }        
                        {instance.ownedBy == "group" && showControls && (instance.state === 'stopped' || instance.state === 'error') && instance.device.type != 'switch' &&
                            <Button variant="danger" title="Reset device" data-toggle="tooltip" data-placement="top" className="ml-3" onClick={() => setShowResetDeviceModel(true)}><SVG name="redo"></SVG></Button>
                        }
                        {(instance.state == 'started' && (instance.controlProtocolTypeInstances.length>0
                         && is_login()) && !is_real() && !isSandbox && user.roles &&(user.roles.includes("ROLE_ADMINISTRATOR") || user.roles.includes("ROLE_SUPER_ADMINISTRATOR") || ((user.roles.includes("ROLE_TEACHER") || user.roles.includes("ROLE_TEACHER_EDITOR")) && user.id === lab.author.id))
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
                            <Button variant="danger" className="ml-3" onClick={() => setShowStopDeviceModel(true)}><SVG name="stop" /></Button>
                        }
                        {instance.ownedBy == "group" && showControls && allInstancesPage && (instance.state != 'stopped' && instance.state != 'error' && instance.state != 'exported' && instance.state != 'reset' && instance.state != 'started') && instance.device.type != 'switch' &&
                            <Button variant="danger" className="ml-3" onClick={() => setShowStopDeviceModel(true)}><SVG name="stop"></SVG></Button>
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
                <Button variant="danger" onClick={() => resetDevice(instance)}>Reset</Button>
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
                <Button variant="danger" onClick={() => stopDevice(instance)}>Stop</Button>
            </Modal.Footer>
        </Modal>
        </>
    )
}


export default InstanceListItem;
