import Noty from 'noty';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import InstanceList from './InstanceList';
import { GroupRoles } from '../Groups/Groups';
import React, { useState, useEffect } from 'react';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import { ListGroup, ListGroupItem, Button, Modal, Spinner } from 'react-bootstrap';

function AllInstancesManager(props = {lab: {}, user: {}, labInstance: {}}) { 
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
        return labInstance.deviceInstances.some(i => (i.state != 'stopped') && (i.state != 'exported') && (i.state != 'error'));    }

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

    return (<>
        {labInstance ?
            <ListGroup>
                <ListGroupItem className="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 className="mb-0">Instances</h4>
                    </div>
                    <div>
                    {isCurrentUserGroupAdmin(viewAs) &&
                        <Button variant="danger" className="ml-2" onClick={() => setShowLeaveLabModal(true)} disabled={hasInstancesStillRunning() || labInstance.state === "creating" || labInstance.state === "deleting"}>Leave lab</Button>
                    }
                    </div>
                </ListGroupItem>
                {labInstance.state === "creating" &&
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        <Spinner animation="border" size="lg" className="text-muted" />

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
                {labInstance.state === "created" &&
                    <InstanceList instances={labInstance.deviceInstances} lab={props.lab} onStateUpdate={onInstanceStateUpdate} showControls={isCurrentUserGroupAdmin(viewAs)}>
                    </InstanceList>
                }
                {labInstance.state === "exporting" &&
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        <Spinner animation="border" size="lg" className="text-muted" />

                        <div className="mt-3">
                            Exporting your instance... This operation may take a moment.
                        </div>
                    </ListGroupItem>
                }
            </ListGroup>
            :
            <ListGroup>
                {isLoadingInstanceState ?
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        <div className="my-3">
                            <div className="dot-bricks"></div>
                        </div>
                        Loading...
                    </ListGroupItem>
                    :
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

export default AllInstancesManager;