import React, { Component } from 'react';
import Routing from 'fos-jsrouting';
import API from '../../../api';
import { ListGroup, Button, Spinner } from 'react-bootstrap';
import { ListGroupItem } from 'react-bootstrap';
import SVG from '../../Display/SVG';
import Noty from 'noty';
import {groupRoles} from '../../Groups/Groups';
import GroupSelect from '../../Form/GroupSelect';
import Select from 'react-select';

const api = API.getInstance();

/**
 * @typedef {Object} DeviceInstance
 * @property {string} name
 * @property {string} uuid
 * @property {string} state
 * @property {string} ownedBy One of `user` or `group`.
 */

/**
 * @typedef {Object} LabInstance
 * @property {DeviceInstance[]} deviceInstances
 */

export class InstanceManager extends Component {
    constructor(props) {
        super(props);

        this.state = {
            viewAs: this.props.user,
            user: this.props.user,
            /** @type {LabInstance} */
            labInstance: this.props.labInstance,
        }

        this.viewAsOptions = this.props.user.groups.map((group) => {
            return this.isGroupElevatedRole(group.role) ? {
                value: group.id,
                label: group.name,
                type: 'group',
                owner: {
                    name: this.props.user.name
                },
                ...group
            } : null;
        }).filter(value => value !== null);
    }

    /**
     * @param {string} role
     * @returns Wether the role is an administrative role or not.
     * @memberof InstanceManager
     */
    isGroupElevatedRole(role) {
        return role === groupRoles.owner || role === groupRoles.admin;
    }

    isCurrentUser = (user) => {
        return this.state.user.id === user.id;
    }

    loadImpersonationOptions = () => {

    }

    fetchInstance = (uuid, type = 'lab') => {
        api.get(Routing.generate('api_get_instance_by_uuid', {uuid, type}))
        .then((response) => this.setState({labInstance: response.data}));
    }

    fetchInstancesByOwner(uuid, ownerType = 'group', instanceType = 'device') {

    }

    onStateUpdate = () => {
        this.fetchInstance(this.state.labInstance.uuid);
    }

    onViewAsChange = (impersonated) => {

    }

    render() {
        return (<>
            View as {this.state.viewAs.name}
            <GroupSelect
                defaultOptions={this.viewAsOptions}
                loadOptions={this.loadImpersonationOptions}
            />

            <InstanceList instances={this.state.labInstance.deviceInstances} onStateUpdate={this.onStateUpdate} />
        </>)
    }
}

export class InstanceList extends Component {
    constructor(props) {
        super(props);
    }

    render() {
        return (
            <ListGroup>
                {this.props.instances.map((deviceInstance, index) => {
                    return <InstanceListItem instance={deviceInstance} key={index} onStateUpdate={this.props.onStateUpdate} />
                })}
            </ListGroup>
        )
    }
}

export class InstanceListItem extends Component {
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

        api.get(Routing.generate('api_start_instance_by_uuid', {uuid: deviceInstance.uuid}))
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

    render() {
        const deviceInstance = this.props.instance;

        return (
            <ListGroupItem className="d-flex">
                <div className="d-flex flex-column">
                    <div>
                        {deviceInstance.device.name} ({deviceInstance.state})
                    </div>
                    <div className="text-muted small">
                        {deviceInstance.uuid}
                    </div>
                </div>

                <div>
                    {this.state.isLoading ?
                        <Button className="ml-3" variant="dark" title="Start device" data-toggle="tooltip" data-placement="top" onClick={() => this.startDevice(deviceInstance)} ref={deviceInstance.uuid} disabled>
                            <Spinner animation="border" size="sm" />
                        </Button>
                    :
                        <Button className="ml-3" variant="success" title="Start device" data-toggle="tooltip" data-placement="top" onClick={() => this.startDevice(deviceInstance)} ref={deviceInstance.uuid}>
                            <SVG name="play" />
                        </Button>
                    }
                </div>
            </ListGroupItem>
        )
    }
}

export default InstanceManager;