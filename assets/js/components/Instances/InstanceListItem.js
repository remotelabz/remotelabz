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

class InstanceListItem extends Component {
    constructor(props) {
        super(props);

        this.state = {
            isLoading: this.isLoading(props.instance),
            logs: [],
            showLogs: false,
            showExport: false,
            isExporting: this.isExporting(props.instance),
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

    toggleShowExport = () => {
        this.setState({ showExport: !this.state.showExport });
    }

    /**
     * @param {DeviceInstance} deviceInstance
     */
    isLoading = (deviceInstance) => {
        return deviceInstance.state === 'starting' || deviceInstance.state === 'stopping';
    }

    isExporting = (deviceInstance) => {
        return deviceInstance.state === 'exporting';
    }

    startDevice = (deviceInstance) => {
        this.setState({ isLoading: true });

        Remotelabz.instances.device.stop(deviceInstance.uuid).then(() => {
            new Noty({
                type: 'success',
                text: 'Instance stop requested.',
                timeout: 5000
            }).show();

            onStateUpdate();
        }).catch(() => {
            new Noty({
                type: 'error',
                text: 'Error while requesting instance stop. Please try again later.',
                timeout: 5000
            }).show();

            setComputing(false)
        })
    }

    function isComputingState(deviceInstance) {
        return deviceInstance.state === 'starting' || deviceInstance.state === 'stopping';
    }

    let controls;

    switch (instance.state) {
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

    exportDeviceTemplate = (deviceInstance, name) => {
        this.setState({ isExporting: true});

        api.get(Routing.generate('api_export_instance_by_uuid', { uuid: deviceInstance.uuid, name: name}))
            .then(() => {
                new Noty({
                    type: 'success',
                    text: 'Instance export requested.',
                    timeout: 5000
                }).show();

            this.props.onStateUpdate();
            })
            .catch(() => {
                new Noty({
                    type: 'error',
                    text: 'Error while requesting instance export. Please try again later.',
                    timeout: 5000
                }).show();

                this.setState({ isExporting: false});
            })
    }

    render() {
        /** @type {DeviceInstance} deviceInstance */
        const deviceInstance = this.props.instance;
        let controls;

        switch (deviceInstance.state) {
            case 'stopped':
                controls = (<Button className="ml-3" variant="success" title="Start device" data-toggle="tooltip" data-placement="top" onClick={() => this.startDevice(deviceInstance)} ref={deviceInstance.uuid} disabled={this.isLoading(deviceInstance)}>
                    <SVG name="play" />
                </Button>);
                break;

            case 'starting':
                controls = (<Button className="ml-3" variant="dark" title="Start device" data-toggle="tooltip" data-placement="top" ref={deviceInstance.uuid} disabled>
                    <Spinner animation="border" size="sm" />
                </Button>);
                break;

            case 'stopping':
                controls = (<Button className="ml-3" variant="dark" title="Stop device" data-toggle="tooltip" data-placement="top" ref={deviceInstance.uuid} disabled>
                    <Spinner animation="border" size="sm" />
                </Button>);
                break;
            
            case 'exporting':
                controls = (<Button className="ml-3" variant="dark" title="Start device" data-toggle="tooltip" data-placement="top" ref={deviceInstance.uuid} disabled>
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
                        onClick={() => this.stopDevice(deviceInstance)}
                        ref={deviceInstance.uuid}
                        disabled={this.isLoading(this.props.instance)}
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
                        <div>
                            {device.name} <InstanceStateBadge state={instance.state} className="ml-1" />
                        </div>
                        <div className="text-muted small">
                            {instance.uuid}
                        </div>
                    </div>

                    <div className="d-flex align-items-center">
                        {(deviceInstance.state == 'stopped' && this.props.isSandbox) &&
                            <div onClick={() => this.toggleShowExport()}>
                            {this.state.showExport ?
                                <Button variant="default"><SVG name="chevron-down"></SVG> Export</Button>
                                :
                                <Button variant="default"><SVG name="chevron-right"></SVG> Export</Button>
                            }
                            </div>
                        }

                        {(deviceInstance.state !== 'stopped'  && deviceInstance.state !== 'exporting') && 
                            <div onClick={() => this.toggleShowLogs()}>
                                {this.state.showLogs ?
                                    <Button variant="default"><SVG name="chevron-down"></SVG> Hide logs</Button>
                                :
                                    <Button variant="default"><SVG name="chevron-right"></SVG> Show logs</Button>
                                }
                            </div>
                        }

                        {(instance.state == 'started' && device.vnc) &&
                            <a
                                target="_blank"
                                rel="noopener noreferrer"
                                href={"/instances/" + instance.uuid + "/view"}
                                className="btn btn-primary ml-3"
                                title="Open VNC console"
                                data-toggle="tooltip"
                                data-placement="top"
                            >
                                <SVG name="external-link" />
                            </a>
                        }

                        {showControls &&
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
                {(deviceInstance.state == 'stopped' && this.state.showExport) &&
                    <InstanceExport deviceInstance={deviceInstance} exportDeviceTemplate={this.exportDeviceTemplate} ></InstanceExport>
                }
            </ListGroupItem>
        )
    }
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