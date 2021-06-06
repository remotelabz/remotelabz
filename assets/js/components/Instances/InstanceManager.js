import Noty from 'noty';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import InstanceList from './InstanceList';
import { GroupRoles } from '../Groups/Groups';
import React, { useState, useEffect } from 'react';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import JitsiCallButton from '../JitsiCall/JitsiCallButton';
import { ListGroup, ListGroupItem, Button, Modal, Spinner } from 'react-bootstrap';

function InstanceManager(props = {lab: {}, user: {}, labInstance: {}, isJitsiCallEnabled: false}) {

    // console.log(props);
    const [labInstance, setLabInstance] = useState(props.labInstance)
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)
    const [viewAs, setViewAs] = useState({ type: 'user', uuid: props.user.uuid, value: props.user.id, label: props.user.name })

    useEffect(() => {
        setLoadingInstanceState(true)
        refreshInstance()
        const interval = setInterval(refreshInstance, 5000)
        return () => {
            clearInterval(interval)
            setLabInstance(null)
            setLoadingInstanceState(true)
        }
    }, [viewAs])

    function refreshInstance() {
        let request

        if (viewAs.type === 'user') {
            request = Remotelabz.instances.lab.getByLabAndUser(props.lab.uuid, viewAs.uuid)
        } else {
            request = Remotelabz.instances.lab.getByLabAndGroup(props.lab.uuid, viewAs.uuid)
        }

        request.then(response => {
            let promises = []
            for (const deviceInstance of response.data.deviceInstances) {
                promises.push(Remotelabz.instances.get(deviceInstance.uuid));
            }

            Promise.all(promises).then(responses => {
                setLabInstance({
                    ...response.data,
                    deviceInstances: responses.map(response => response.data)
                })
                setLoadingInstanceState(false)
            }).catch(error => {
                new Noty({
                    text: 'An error happened while fetching instances state. Please try refreshing this page. If this error persist, please contact an administrator.',
                    type: 'error'
                }).show()
            })
        }).catch(error => {
            if (error.response) {
                if (error.response.status <= 500) {
                    setLabInstance(null)
                    setLoadingInstanceState(false)
                } else {
                    new Noty({
                        text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                        type: 'error'
                    }).show()
                }
            }
        })
    }

    function isGroupElevatedRole(role) {
        return role === GroupRoles.Owner || role === GroupRoles.Admin
    }

    function isCurrentUserGroupAdmin(group) {
        if (group.type === 'user') {
            return true
        }

        const _group = props.user.groups.find(g => g.uuid === group.uuid);
        return _group ? (_group.role == 'admin' || _group.role == 'owner') : false
    }

    function isOwnedByGroup() {
        return labInstance.ownedBy == "group"
    }

    function hasInstancesStillRunning() {
        return labInstance.deviceInstances.some(i => i.state != 'stopped')
    }

    async function onInstanceStateUpdate() {
        // const response = await Remotelabz.instances.get(labInstance.uuid, 'lab')
        // setLabInstance(response.data)
    }

    function onViewAsChange(option) {
        setViewAs(option);
    }

    async function onJoinLab() {
        setLoadingInstanceState(true)

        try {
            const response = await Remotelabz.instances.lab.create(props.lab.uuid, viewAs.uuid, viewAs.type)
            setLoadingInstanceState(false)
            setLabInstance(response.data)
        } catch (error) {
            console.error(error)
            new Noty({
                text: 'There was an error creating an instance. Please try again later.',
                type: 'error'
            }).show()
            setLoadingInstanceState(false)
        }
    }

    async function onLeaveLab() {
        setShowLeaveLabModal(false)
        setLoadingInstanceState(true)

        try {
            await Remotelabz.instances.lab.delete(labInstance.uuid)
            setLabInstance({ ...labInstance, state: "deleting" })
        } catch (error) {
            console.error(error)
            new Noty({
                text: 'An error happened while leaving the lab. Please try again later.',
                type: 'error'
            }).show()
            setLoadingInstanceState(false)
        }
    }

    function onJitsiCallStarted() {
        let labInstance = {...labInstance};
        labInstance.jitsiCall.state = 'started';
        this.setState({labInstance});
    }

    render() {
        return (<>
            {!this.props.isSandbox &&
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
            }

                        <div className="mt-3">
                            Creating your instance... This operation may take a moment.
                        </div>
                    </ListGroupItem>
                }
                {labInstance.state === "deleting" &&
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        <Spinner animation="border" size="lg" className="text-muted" />

                            <div className="mt-3">
                                Deleting your instance... This operation may take a moment.
                            </div>
                        </ListGroupItem>
                    }
                    {this.state.labInstance.state === "created" &&
                        <InstanceList instances={this.state.labInstance.deviceInstances} lab={this.state.lab} onStateUpdate={this.onStateUpdate} showControls={this.isCurrentUserGroupAdmin(this.state.viewAs)} isSandbox={this.props.isSandbox} >
                        </InstanceList>
                    }
                </ListGroup>
                :
                <ListGroup>
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        {viewAs.type === 'user' ?
                            <div className="d-flex align-items-center justify-content-center flex-column">
                                You haven&apos;t joined this lab yet.

                                <div className="mt-3">
                                    <Button onClick={onJoinLab} disabled={isLoadingInstanceState}>Join this lab</Button>
                                </div>
                            </div>
                            :
                            <div className="d-flex align-items-center justify-content-center flex-column">
                                This group hasn&apos;t joined this lab yet.

                                {isGroupElevatedRole(viewAs.role) &&
                                    <div className="mt-3">
                                        <Button onClick={onJoinLab} disabled={isLoadingInstanceState}>Join this lab</Button>
                                    </div>
                                }
                            </div>
                        }
                    </ListGroupItem>
                }
            </ListGroup>
        }
        <Modal show={showLeaveLabModal} onHide={() => setShowLeaveLabModal(false)}>
            <Modal.Header closeButton>
                <Modal.Title>Leave lab</Modal.Title>
            </Modal.Header>
            <Modal.Body>
                If you leave the lab, <strong>all your instances will be deleted and all virtual machines associed will be destroyed.</strong> Are you sure you want to leave this lab ?
            </Modal.Body>
            <Modal.Footer>
                <Button variant="default" onClick={() => setShowLeaveLabModal(false)}>Close</Button>
                <Button variant="danger" onClick={onLeaveLab}>Leave</Button>
            </Modal.Footer>
        </Modal>
    </>)
}

export default InstanceManager;