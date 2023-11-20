import Noty from 'noty';
import API from '../../api';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import Routing from 'fos-jsrouting';
import React, { useState, useEffect, Component } from 'react';
import InstanceStateBadge from './InstanceStateBadge';
import InstanceExport from './InstanceExport';
import { ListGroupItem, Button, Spinner } from 'react-bootstrap';

const api = API.getInstance();

function InstanceListItem({ instance, showControls, onStateUpdate, isSandbox, lab }) {
    const [isLoading, setLoading] = useState(true)
    const [isComputing, setComputing] = useState(false)
    const [isExporting, setExporting] = useState(false)
    const [logs, setLogs] = useState([])
    const [showLogs, setShowLogs] = useState(false)
    const [showExport, setShowExport] = useState(false)
    //console.log("isSandbox",isSandbox);
    const [device, setDevice] = useState({ name: '' })
    
    //console.log("instanceListItem");
    //console.log(instance.device.name);
   
    useEffect(() => {
        fetchLogs()
        const interval = setInterval(fetchLogs, 5000)
        Remotelabz.devices.get(instance.device.id).then(response => {
            setDevice(response.data)
            setLoading(false)
        //console.log(response.data)
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
                    text: 'Error while requesting instance start. Please try again later.',
                    timeout: 5000
                }).show();
            }
            setComputing(false)
        })
    }

    function stopDevice(deviceInstance) {
        setComputing(true)

        Remotelabz.instances.device.stop(deviceInstance.uuid).then(() => {
            new Noty({
                type: 'success',
                text: 'Instance stop requested.',
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
                    text: 'Error while requesting instance stop. Please try again later.',
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
    
    //console.log(instance);
    let controls;
    
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

    return (
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
                        {( (instance.state == 'stopped' || instance.state == 'exported')&&  (lab.devices.length == 1) && isSandbox) &&
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
    )
}
// class InstanceListItem extends Component {
//     constructor(props) {
//         super(props);

//         console.log("InstanceListItem props", props)

//         this.state = {
//             isLoading: this.isLoading(props.instance),
//             logs: [],
//             showLogs: false,
//             device: {
//                 name: ''
//             }
//         }

//         this.fetchLogs().then(() => this.interval = setInterval(this.fetchLogs, 5000));
//         Remotelabz.devices.get(this.props.instance.device.id)
//             .then(response => this.setState({ device: response.data }))
//     }

//     componentDidMount() {

//     }

//     componentWillUnmount() {
//         clearInterval(this.interval);
//     }

//     fetchLogs = () => {
//         return Remotelabz.instances.device.logs(this.props.instance.uuid)
//         .then(response => {
//             this.setState({ logs: response.data });
//         })
//         .catch(error => {
//             if (error.response.status === 404) {
//                 this.setState({ logs: [] });
//             } else {
//                 new Noty({
//                     text: 'An error happened while fetching instance logs. If this error persist, please contact an administrator.',
//                     type: 'error'
//                 }).show();
//                 clearInterval(this.interval);
//             }
//         });
//     }

//     toggleShowLogs = () => {
//         this.setState({ showLogs: !this.state.showLogs });
//     }

//     /**
//      * @param {DeviceInstance} deviceInstance
//      */
//     isLoading = (deviceInstance) => {
//         return deviceInstance.state === 'starting' || deviceInstance.state === 'stopping';
//     }

//     startDevice = (deviceInstance) => {
//         this.setState({ isLoading: true });

//         api.get(Routing.generate('api_start_instance_by_uuid', { uuid: deviceInstance.uuid }))
//             .then(() => {
//                 new Noty({
//                     type: 'success',
//                     text: 'Instance start requested.',
//                     timeout: 5000
//                 }).show();

//                 this.props.onStateUpdate();
//             })
//             .catch(() => {
//                 new Noty({
//                     type: 'error',
//                     text: 'Error while requesting instance start. Please try again later.',
//                     timeout: 5000
//                 }).show();

//                 this.setState({ isLoading: false });
//             })
//     }

//     stopDevice = (deviceInstance) => {
//         this.setState({ isLoading: true });

//         api.get(Routing.generate('api_stop_instance_by_uuid', { uuid: deviceInstance.uuid }))
//             .then(() => {
//                 new Noty({
//                     type: 'success',
//                     text: 'Instance stop requested.',
//                     timeout: 5000
//                 }).show();

//                 this.props.onStateUpdate();
//             })
//             .catch(() => {
//                 new Noty({
//                     type: 'error',
//                     text: 'Error while requesting instance stop. Please try again later.',
//                     timeout: 5000
//                 }).show();

//                 this.setState({ isLoading: false });
//             })
//     }

//     render() {
//         /** @type {DeviceInstance} deviceInstance */
//         const deviceInstance = this.props.instance;
//         let controls;

//         switch (deviceInstance.state) {
//             case 'stopped':
//                 controls = (<Button className="ml-3" variant="success" title="Start device" data-toggle="tooltip" data-placement="top" onClick={() => this.startDevice(deviceInstance)} ref={deviceInstance.uuid} disabled={this.isLoading(deviceInstance)}>
//                     <SVG name="play" />
//                 </Button>);
//                 break;

//             case 'starting':
//                 controls = (<Button className="ml-3" variant="dark" title="Start device" data-toggle="tooltip" data-placement="top" ref={deviceInstance.uuid} disabled>
//                     <Spinner animation="border" size="sm" />
//                 </Button>);
//                 break;

//             case 'stopping':
//                 controls = (<Button className="ml-3" variant="dark" title="Stop device" data-toggle="tooltip" data-placement="top" ref={deviceInstance.uuid} disabled>
//                     <Spinner animation="border" size="sm" />
//                 </Button>);
//                 break;

//             case 'started':
//                 controls = (
//                     <Button
//                         className="ml-3"
//                         variant="danger"
//                         title="Stop device"
//                         data-toggle="tooltip"
//                         data-placement="top"
//                         onClick={() => this.stopDevice(deviceInstance)}
//                         ref={deviceInstance.uuid}
//                         disabled={this.isLoading(this.props.instance)}
//                     >
//                         <SVG name="stop" />
//                     </Button>
//                 );
//                 break;
//         }

//         return (
//             <ListGroupItem>
//                 <div className="d-flex justify-content-between">
//                     <div className="d-flex flex-column">
//                         <div>
//                             {this.state.device.name} <InstanceStateBadge state={deviceInstance.state} className="ml-1" />
//                         </div>
//                         <div className="text-muted small">
//                             {deviceInstance.uuid}
//                         </div>
//                     </div>

//                     <div className="d-flex align-items-center">
//                         {deviceInstance.state !== 'stopped' && 
//                             <div onClick={() => this.toggleShowLogs()}>
//                                 {this.state.showLogs ?
//                                     <Button variant="default"><SVG name="chevron-down"></SVG> Hide logs</Button>
//                                 :
//                                     <Button variant="default"><SVG name="chevron-right"></SVG> Show logs</Button>
//                                 }
//                             </div>
//                         }

//                         {(deviceInstance.state == 'started' && this.state.device.vnc) &&
//                             <a
//                                 target="_blank"
//                                 rel="noopener noreferrer"
//                                 href={"/instances/" + deviceInstance.uuid + "/view"}
//                                 className="btn btn-primary ml-3"
//                                 title="Open VNC console"
//                                 data-toggle="tooltip"
//                                 data-placement="top"
//                             >
//                                 <SVG name="external-link" />
//                             </a>
//                         }

//                         {this.props.showControls &&
//                             controls
//                         }
//                     </div>
//                 </div>
//                 {(deviceInstance.state !== 'stopped' && this.state.showLogs) && 
//                     <pre className="d-flex flex-column mt-2">
//                         {(deviceInstance.state != 'stopped' && this.state.logs) && this.state.logs.map((log, index) => {
//                             return <code className="p-1" key={log.id}>[{log.createdAt}] {log.content}</code>;
//                         })}
//                     </pre>
//                 }
//             </ListGroupItem>
//         )
//     }
// }

export default InstanceListItem;