import Noty from 'noty';
import API from '../../api';
import Remotelabz from '../API';
import Routing from 'fos-jsrouting';
import React, { Component } from 'react';
import InstanceList from './InstanceList';
import { GroupRoles } from '../Groups/Groups';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import { ListGroup, ListGroupItem, Button, Modal, Spinner } from 'react-bootstrap';

const api = API.getInstance();
const getenv = require('getenv')

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
                this.setState({ labInstance: response.data });
            })
            .catch(error => {
                if (error.response.status === 404) {
                    this.setState({ labInstance: null });
                } else {
                    new Noty({
                        text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                        type: 'error'
                    }).show();
                    clearInterval(this.interval);
                }
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

    isOwnedByGroup() {
        return this.state.labInstance.ownedBy == "group";
    }

    isCallStarted() {
        return this.state.labInstance.isCallStarted;
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
        const response = Remotelabz.instances.get(uuid, type);
        response.then(response => this.setState({ labInstance: response.data }));
        return response;
    }

    /**
     * Get device instance state by UUID.
     *
     * @memberof InstanceManager
     */
    fetchInstanceState = (uuid) => {
        return api.get(Routing.generate('api_get_instance_state_by_uuid', { uuid }));
    }

    fetchInstancesByOwner(uuid, ownerType = 'group', instanceType = 'device') {
        return api.get(Routing.generate('api_get_instance_by_' + ownerType, { uuid, type: instanceType }), { validateStatus: function (status) { return status < 500 } });
    }

    deleteInstance = (uuid) => {
        return api.delete(Routing.generate('api_delete_instance', { uuid }))
            .then(response => { this.setState({ labInstance: null }); return response; });
    }

    onStateUpdate = () => {
        this.fetchInstance(this.state.labInstance.uuid);
    }

    onViewAsChange = option => {
        if (option != this.state.viewAs) {
            clearInterval(this.interval);
            this.setState({ isLoadingInstanceState: true });

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
                            this.setState({ labInstance: response.data });
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
            .finally(() => this.setState({ isLoadingInstanceState: false }));
        }
    }

    onJoinLab = () => {
        const viewAs = this.state.viewAs;
        this.setState({ isLoadingInstanceState: true });
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

        Remotelabz.instances.lab.delete(this.state.labInstance.uuid)
            .then(() => this.setState({ labInstance: { ...this.state.labInstance, state: "deleting" } }))
            .catch(() => {
                new Noty({
                    text: 'An error happened while leaving the lab. Please try again later.',
                    type: 'error'
                }).show();
            })
            .finally(() => {
                this.setState({ isLoadingInstanceState: false });
            })
    }

    onLeaveLabButtonClick = () => this.setState({ showLeaveLabModal: true });

    onLeaveLabModalClose = () => this.setState({ showLeaveLabModal: false });

    onMakeACallButtonClick = () => {
        Remotelabz.instances.lab.startCall(this.state.lab.uuid, this.state.labInstance.owner.uuid)
            .then(() => this.setState({ labInstance: { ...this.state.labInstance, isCallStarted: true}}))
    }

    onJoinCallButtonClick = () => {
        let user_name = this.state.user.name;
        let user_email = this.state.user.email;
        let user_id = this.state.user.uuid;
        
        if (this.state.labInstance.ownedBy == "group") {
            Remotelabz.instances.lab.joinCall(this.state.lab.uuid, this.state.labInstance.owner.uuid, user_name, user_email)
                .then(response => {
                    window.open(response.data);
                })
        }
    }
    
    render() {
        let callButton;

        if (this.state.labInstance && this.isOwnedByGroup() && getenv.bool('ENABLE_JITSI_CALL', false)) {
            if(this.isCurrentUserGroupAdmin(this.state.viewAs)) {
                if(this.isCallStarted()) {
                    callButton = <Button variant="primary" onClick={this.onJoinCallButtonClick}>Join call</Button>;
                }
                else {
                    callButton = <Button variant="success" onClick={this.onMakeACallButtonClick}>Make a Call</Button>;
                }
            }
            else {
                callButton = <Button variant="primary" onClick={this.onJoinCallButtonClick} disabled={!this.isCallStarted()}>Join call</Button>
            }
        }
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

            {this.state.labInstance ?
                <ListGroup>
                    <ListGroupItem className="d-flex align-items-center justify-content-between">
                        <h4 className="mb-0">Instances</h4>
                        {callButton}
                        {this.isCurrentUserGroupAdmin(this.state.viewAs) &&
                            <Button variant="danger" onClick={this.onLeaveLabButtonClick} disabled={this.hasInstancesStillRunning() || this.state.labInstance.state === "creating" || this.state.labInstance.state === "deleting"}>Leave lab</Button>
                        }
                    </ListGroupItem>
                    {this.state.labInstance.state === "creating" &&
                        <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                            <Spinner animation="border" size="lg" className="text-muted" />

                            <div className="mt-3">
                                Creating your instance... This operation may take a moment.
                            </div>
                        </ListGroupItem>
                    }
                    {this.state.labInstance.state === "deleting" &&
                        <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                            <Spinner animation="border" size="lg" className="text-muted" />

                            <div className="mt-3">
                                Deleting your instance... This operation may take a moment.
                            </div>
                        </ListGroupItem>
                    }
                    {this.state.labInstance.state === "created" &&
                        <InstanceList instances={this.state.labInstance.deviceInstances} lab={this.state.lab} onStateUpdate={this.onStateUpdate} showControls={this.isCurrentUserGroupAdmin(this.state.viewAs)}>
                        </InstanceList>
                    }
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

                                {this.isGroupElevatedRole(this.state.viewAs.role) &&
                                    <div className="mt-3">
                                        <Button onClick={this.onJoinLab} disabled={this.state.isLoadingInstanceState}>Join this lab</Button>
                                    </div>
                                }
                            </div>
                        }
                    </ListGroupItem>
                </ListGroup>
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
        </>)
    }
}

export default InstanceManager;