import Noty from 'noty';
import API from '../../api';
import SVG from '../Display/SVG';
import Routing from 'fos-jsrouting';
import React, { Component } from 'react';
import InstanceStateBadge from './InstanceStateBadge';
import { ListGroupItem, Button, Spinner } from 'react-bootstrap';

const api = API.getInstance();

class InstanceListItem extends Component {
    constructor(props) {
        super(props);

        this.state = {
            isLoading: this.isLoading(props.instance)
        }
    }

    /**
     * @param {DeviceInstance} deviceInstance
     */
    isLoading = (deviceInstance) => {
        return deviceInstance.state === 'starting' || deviceInstance.state === 'stopping';
    }

    startDevice = (deviceInstance) => {
        this.setState({ isLoading: true });

        api.get(Routing.generate('api_start_instance_by_uuid', { uuid: deviceInstance.uuid }))
            .then(() => {
                new Noty({
                    type: 'success',
                    text: 'Instance start requested.',
                    timeout: 5000
                }).show();

                this.props.onStateUpdate();
            })
            .catch(() => {
                new Noty({
                    type: 'error',
                    text: 'Error while requesting instance start. Please try again later.',
                    timeout: 5000
                }).show();

                this.setState({ isLoading: false });
            })
    }

    stopDevice = (deviceInstance) => {
        this.setState({ isLoading: true });

        api.get(Routing.generate('api_stop_instance_by_uuid', { uuid: deviceInstance.uuid }))
            .then(() => {
                new Noty({
                    type: 'success',
                    text: 'Instance stop requested.',
                    timeout: 5000
                }).show();

                this.props.onStateUpdate();
            })
            .catch(() => {
                new Noty({
                    type: 'error',
                    text: 'Error while requesting instance stop. Please try again later.',
                    timeout: 5000
                }).show();

                this.setState({ isLoading: false });
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
            <ListGroupItem className="d-flex justify-content-between">
                <div className="d-flex flex-column">
                    <div>
                        {deviceInstance.device.name} <InstanceStateBadge state={deviceInstance.state} className="ml-1" />
                    </div>
                    <div className="text-muted small">
                        {deviceInstance.uuid}
                    </div>
                </div>

                <div className="d-flex align-items-center">
                    {(deviceInstance.state == 'started' && deviceInstance.device.networkInterfaces.some(nic => nic.accessType === 'VNC')) &&
                        <a
                            target="_blank"
                            rel="noopener noreferrer"
                            href={"/instances/" + deviceInstance.uuid + "/view"}
                            className="btn btn-primary ml-3"
                            title="Open VNC console"
                            data-toggle="tooltip"
                            data-placement="top"
                        >
                            <SVG name="external-link" />
                        </a>
                    }

                    {this.props.showControls &&
                        controls
                    }
                </div>
            </ListGroupItem>
        )
    }
}

export default InstanceListItem;