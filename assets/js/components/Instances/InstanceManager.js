import React, { Component } from 'react';
import Routing from 'fos-jsrouting';
import API from '../../api';
import { ListGroup, Button, Spinner, Modal, Badge } from 'react-bootstrap';
import { ListGroupItem } from 'react-bootstrap';
import SVG from '../Display/SVG';
import Noty from 'noty';
import {GroupRoles} from '../Groups/Groups';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import Remotelabz from '../API';

const api = API.getInstance();

/**
 * @typedef {Object} Instancier
 * @property {string} uuid
 * 
 * @typedef {"user"} GroupUserRoles
 * @typedef {"admin"|"owner"} GroupAdministrativeRoles
 * 
 * @typedef {Object} DeviceInstance
 * @property {string} name
 * @property {string} uuid
 * @property {string} state
 * @property {"user"|"group"} ownedBy
 * 
 * @typedef {Object} LabInstance
 * @property {DeviceInstance[]} deviceInstances
 * 
 * @typedef {Object} InstanceManagerProps
 * @property {Object} Lab
 * 
 * 
 * @typedef {Object} ViewAsOption
 * @property {Instancier} [owner]
 */

/**
 * @extends {Component<InstanceManagerProps, DeviceInstance>}
 */
export class InstanceManager extends Component {
    constructor(props) {
        super(props);

        /** @type {ViewAsOption|User} */
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

        const viewAsGroupsOptions = this.props.user.groups.map((group) => {
            return {
                value: group.uuid,
                label: group.name,
                type: 'group',
                owner: group.role === 'owner' ? props.user : {
                    id: group.owner.id,
                    name: group.owner.name
                },
                parent: group.parent,
                ...group
            };
        }).filter(value => value !== null);

        this.viewAsOptions = [{
            label: 'User',
            options: [viewAsUserOptions]
        }, {
            label: 'Groups',
            options: viewAsGroupsOptions
        }];

        this.interval = setInterval(() => {
            this.fetchInstance(this.state.labInstance.uuid, 'lab')
            .then(response => {
                this.setState({labInstance: response.data});
            })
            .catch(() => {
                new Noty({
                    text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                    type: 'error'
                }).show();
                clearInterval(this.interval);
            });
        }, 5000);
    }

    /**
     * @param {GroupUserRoles|GroupAdministrativeRoles} role
     * @returns Wether the role is an administrative role or not.
     * @memberof InstanceManager
     */
    isGroupElevatedRole(role) {
        return role === GroupRoles.Owner || role === GroupRoles.Admin;
    }

    isCurrentUserGroupAdmin(group) {
        if (group.type === 'user') {
            return true;
        }

        const _group = this.state.user.groups.find(g => g.uuid === group.uuid);
        return _group ? (_group.role == 'admin' || _group.role == 'owner') : false;
    }

    isCurrentUser = (user) => {
        return this.state.user.id === user.id;
    }

    hasInstancesStillRunning = () => {
        return this.state.labInstance.deviceInstances.some(i => i.state != 'stopped');
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

    /**
     * Get device instance state by UUID.
     *
     * @memberof InstanceManager
     */
    fetchInstanceState = (uuid) => {
        return api.get(Routing.generate('api_get_instance_state_by_uuid', {uuid}));
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
            clearInterval(this.interval);
            this.setState({isLoadingInstanceState: true});

            let request;

            if (option.type === 'user') {
                request = Remotelabz.instances.lab.getByLabAndUser(this.state.lab.uuid, option.uuid);
            } else {
                request = Remotelabz.instances.lab.getByLabAndGroup(this.state.lab.uuid, option.uuid);
            }

            request.then(response => {
                this.setState({ viewAs: option, labInstance: response.data });

                this.interval = setInterval(() => {
                    this.fetchInstance(this.state.labInstance.uuid, 'lab')
                    .then(response => {
                        this.setState({labInstance: response.data});
                    })
                    .catch(error => {
                        console.error(error);
                        new Noty({
                            text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                            type: 'error'
                        }).show();
                        clearInterval(this.interval);
                    })
                }, 5000);
            })
            .catch(error => {
                if (status <= 500) {
                    this.setState({ viewAs: option, labInstance: null });
                } else {
                    console.error('fetchInstanceByOwner returned an error :', error.response);
                    new Noty({
                        text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                        type: 'error'
                    }).show();
                }
            })
            .finally(() => this.setState({isLoadingInstanceState: false}));
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
                        { this.isCurrentUserGroupAdmin(this.state.viewAs) &&
                            <Button variant="danger" onClick={this.onLeaveLabButtonClick} disabled={this.hasInstancesStillRunning()}>Leave lab</Button>
                        }

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
                    <InstanceList instances={this.state.labInstance.deviceInstances} lab={this.state.lab} onStateUpdate={this.onStateUpdate} showControls={this.isCurrentUserGroupAdmin(this.state.viewAs)}/>
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

const InstanceList = (props) => props.instances.map((deviceInstance, index) =>
    <InstanceListItem instance={deviceInstance} key={index} {...props} />
);

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

    stopDevice = (deviceInstance) => {
        this.setState({ isLoading: true });

        api.get(Routing.generate('api_stop_instance_by_uuid', {uuid: deviceInstance.uuid}))
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
                controls = (<>
                    {deviceInstance.device.networkInterfaces.some(nic => nic.accessType === 'VNC') &&
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
                </>);
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

                { this.props.showControls &&
                    <div className="d-flex align-items-center">
                        { controls }
                    </div>
                }
            </ListGroupItem>
        )
    }
}

class InstanceStateBadge extends Component {
    constructor(props) {
        super(props);

        this.state = {
            state: props.state
        };
    }

    render() {
        let badge;

        switch (this.props.state) {
            case 'stopped':
                badge = <Badge variant="default" {...this.props}>Stopped</Badge>;
                break;

            case 'starting':
                badge = <Badge variant="warning" {...this.props}>Starting</Badge>;
                break;

            case 'stopping':
                badge = <Badge variant="warning" {...this.props}>Stopping</Badge>
                break;

            case 'started':
                badge = <Badge variant="success" {...this.props}>Started</Badge>
                break;

            case 'error':
                badge = <Badge variant="danger" {...this.props}>Error</Badge>
                break;

            default:
                badge = <Badge variant="default" {...this.props}>{this.state.state}</Badge>
        }

        return badge;
    }
}

export default InstanceManager;