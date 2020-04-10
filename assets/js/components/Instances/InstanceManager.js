import React, { Component } from 'react';
import Routing from 'fos-jsrouting';
import API from './../../api';
import { ListGroup, Button, Spinner, Modal } from 'react-bootstrap';
import { ListGroupItem } from 'react-bootstrap';
import SVG from './../Display/SVG';
import Noty from 'noty';
import {groupRoles} from './../Groups/Groups';
import InstanceOwnerSelect from './InstanceOwnerSelect';

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

        let viewAsUserOptions = {
            owner: null,
            type: 'user',
            children: [],
            value: props.user.id,
            label: props.user.name,
            ...props.user
        };

        this.state = {
            lab: this.props.lab,
            user: this.props.user,
            showLeaveLabModal: false,
            viewAs: viewAsUserOptions,
            isLoadingInstanceState: false,
            /** @type {LabInstance} */
            labInstance: this.props.labInstance,
        }

        let viewAsGroupsOptions = this.props.user.groups.map((group) => {
            return this.isGroupElevatedRole(group.role) ? {
                value: group.uuid,
                label: group.name,
                type: 'group',
                owner: group.role === 'owner' ? props.user : {
                    id: group.owner.id,
                    name: group.owner.name
                },
                parent: group.parent,
                ...group
            } : null;
        }).filter(value => value !== null);

        this.viewAsOptions = [{
            label: 'User',
            options: [viewAsUserOptions]
        }, {
            label: 'Groups',
            options: viewAsGroupsOptions
        }];
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

    hasInstancesStillRunning = () => {
        return this.state.labInstance.deviceInstances.some(deviceInstance => {
            return deviceInstance.state != 'stopped'
        });
    }

    filterViewAsOptions = (input) => {
        return this.viewAsOptions[1].options.filter(i => i.name.toLowerCase().includes(input.toLowerCase()));
    }

    loadImpersonationOptions = (input) => {
        return new Promise(resolve => {
            const value = this.filterViewAsOptions(input);
            console.log('value', value);
            resolve(value);
        });
    }

    createInstance = (labUuid, ownerUuid, ownerType = 'group') => {
        return api.post(Routing.generate('api_create_instance'), {
            'lab': labUuid,
            'instancier': ownerUuid,
            'instancierType': ownerType
        });
    }

    fetchInstance = (uuid, type = 'lab') => {
        return api.get(Routing.generate('api_get_instance_by_uuid', {uuid, type}))
        .then(response => { this.setState({labInstance: response.data}); return response; });
    }

    fetchInstancesByOwner(uuid, ownerType = 'group', instanceType = 'device') {
        return api.get(Routing.generate('api_get_instance_by_' + ownerType, {uuid, type: instanceType}), { validateStatus: function (status) { return status < 500 } });
    }

    deleteInstance = (uuid) => {
        return api.delete(Routing.generate('api_delete_instance', {uuid}))
        .then(response => { this.setState({labInstance: null}); return response; });
    }

    onStateUpdate = () => {
        this.fetchInstance(this.state.labInstance.uuid);
    }

    onViewAsChange = option => {
        if (option != this.state.viewAs) {
            this.setState({isLoadingInstanceState: true});

            this.fetchInstancesByOwner(option.uuid, option.type, 'lab')
            .then(response => {
                const status = response.status;
                if (status >= 400) {
                    this.setState({ viewAs: option, labInstance: null });
                } else {
                    this.setState({ viewAs: option, labInstance: response.data });
                }

                this.setState({isLoadingInstanceState: false});
            })
            .catch(error => {
                console.error('fetchInstanceByOwner returned an error :', error.response);
            });
        }
    }

    onJoinLab = () => {
        const viewAs = this.state.viewAs;
        this.setState({isLoadingInstanceState: true});
        this.createInstance(this.state.lab.uuid, viewAs.uuid, viewAs.type)
            .then(response => {
                this.setState({
                    isLoadingInstanceState: false,
                    labInstance: response.data
                });
            })
            .catch(() => {
                new Noty({
                    text: 'There was an error creating an instance. Please try again later.',
                    type: 'error'
                }).show();
            });
    }

    onLeaveLab = () => {
        this.setState({ showLeaveLabModal: false, isLoadingInstanceState: true });

        this.deleteInstance(this.state.labInstance.uuid)
            .catch(() => {
                new Noty({
                    text: 'An error happened while leaving the lab. Please try again later.',
                    type: 'error'
                }).show();
            })
            .finally(() => {
                this.setState({ isLoadingInstanceState: false });
            });
    };

    onLeaveLabButtonClick = () => this.setState({ showLeaveLabModal: true });

    onLeaveLabModalClose = () => this.setState({ showLeaveLabModal: false });

    render() {
        return (<>
            <div className="d-flex align-items-center mb-2">
                <div>View as : </div>
                <div className="flex-grow-1 ml-2">
                    <InstanceOwnerSelect
                        options={this.viewAsOptions}
                        defaultValue={this.viewAsOptions[1].options[0]}
                        onChange={this.onViewAsChange}
                        isDisabled={this.state.isLoadingInstanceState}
                        value={this.state.viewAs}
                    />
                </div>
            </div>

            { this.state.labInstance ?
                <ListGroup>
                    <ListGroupItem className="d-flex align-items-center justify-content-between">
                        <h4 className="mb-0">Instances</h4>
                        <Button variant="danger" onClick={this.onLeaveLabButtonClick} disabled={this.hasInstancesStillRunning()}>Leave lab</Button>

                        <Modal show={this.state.showLeaveLabModal} onHide={this.onLeaveLabModalClose}>
                            <Modal.Header closeButton>
                                <Modal.Title>Leave lab</Modal.Title>
                            </Modal.Header>
                            <Modal.Body>
                                If you leave the lab, <strong>all your instances will be deleted and all virtual machines associed will be destroyed.</strong> Are you sure you want to leave this lab ?
                            </Modal.Body>
                            <Modal.Footer>
                                <Button variant="default" onClick={this.onLeaveLabModalClose}>Close</Button>
                                <Button variant="danger" onClick={this.onLeaveLab}>Leave</Button>
                            </Modal.Footer>
                        </Modal>
                    </ListGroupItem>
                    <InstanceList instances={this.state.labInstance.deviceInstances} onStateUpdate={this.onStateUpdate} />
                </ListGroup>
            :
                <ListGroup>
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        {this.state.viewAs.type === 'user' ?
                            <div className="d-flex align-items-center justify-content-center flex-column">
                                You haven&apos;t joined this lab yet.

                                <div className="mt-3">
                                    <Button onClick={this.onJoinLab} disabled={this.state.isLoadingInstanceState}>Join this lab</Button>
                                </div>
                            </div>
                        :
                            <div className="d-flex align-items-center justify-content-center flex-column">
                                This group hasn&apos;t joined this lab yet.

                                { this.isGroupElevatedRole(this.state.viewAs.role) &&
                                    <div className="mt-3">
                                        <Button onClick={this.onJoinLab} disabled={this.state.isLoadingInstanceState}>Join this lab</Button>
                                    </div>
                                }
                            </div>
                        }
                    </ListGroupItem>
                </ListGroup>
            }
        </>)
    }
}

export class InstanceList extends Component {
    constructor(props) {
        super(props);
    }

    render() {
        return this.props.instances.map((deviceInstance, index) => {
            return <InstanceListItem instance={deviceInstance} key={index} {...this.props} />
        })
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
        /** @type {DeviceInstance} deviceInstance */
        const deviceInstance = this.props.instance;
        let controls;

        switch (deviceInstance.state) {
            case 'stopped':
                controls = (<Button className="ml-3" variant="success" title="Start device" data-toggle="tooltip" data-placement="top" onClick={() => this.startDevice(deviceInstance)} ref={deviceInstance.uuid} disabled={this.state.isLoading}>
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
                controls = (<Button className="ml-3" variant="danger" title="Stop device" data-toggle="tooltip" data-placement="top" onClick={() => this.stopDevice(deviceInstance)} ref={deviceInstance.uuid} disabled={this.state.isLoading}>
                    <SVG name="stop" />
                </Button>);
                break;
        }

        return (
            <ListGroupItem className="d-flex justify-content-between">
                <div className="d-flex flex-column">
                    <div>
                        {deviceInstance.device.name} ({deviceInstance.state})
                    </div>
                    <div className="text-muted small">
                        {deviceInstance.uuid}
                    </div>
                </div>

                <div className="d-flex align-items-center">
                    { controls }
                </div>
            </ListGroupItem>
        )
    }
}

export default InstanceManager;