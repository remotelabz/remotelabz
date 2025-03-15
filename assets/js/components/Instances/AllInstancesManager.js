import Noty from 'noty';
import Remotelabz from '../API';
import InstanceList from './InstanceList';
import { GroupRoles } from '../Groups/Groups';
import React, { useState, useEffect } from 'react';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import { ListGroup, ListGroupItem, Button, Modal, Spinner } from 'react-bootstrap';
import moment from 'moment/moment';

function AllInstancesManager(props) { 
    const [labInstance, setLabInstance] = useState([])
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [showStopLabModal, setShowStopLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)

    useEffect(()=> {
        setLabInstance(props.props);
    })
    function hasInstancesStillRunning() {
        return labInstance.deviceInstances.some(i => (i.state != 'stopped') && (i.state != 'exported') && (i.state != 'error') && (i.state != 'reset'));
        //return false;
    }

    async function onInstanceStateUpdate() {
        // const response = await Remotelabz.instances.get(labInstance.uuid, 'lab')
        // setLabInstance(response.data)
    }

    async function onLeaveLab() {
        setShowLeaveLabModal(false)
        setLoadingInstanceState(true)
        try {
            Remotelabz.instances.lab.delete(labInstance.uuid)
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

    function stopDevices() {
        setShowStopLabModal(false);
        var deviceInstancesToStop = [];
        let promises = []
        if (hasInstancesStillRunning()) {
            promises.push(()=> {
                for(let deviceInstance of labInstance.deviceInstances) {
                    if (deviceInstance.state != "stopped" && deviceInstance.state != "exported" && deviceInstance.state != "error" && deviceInstance.state != "reset") {
                        try {
                            Remotelabz.instances.device.stop(deviceInstance.uuid)
                        } catch (error) {
                            console.error(error)
                            new Noty({
                                text: 'An error happened while stopping a device. Please try again later.',
                                type: 'error'
                            }).show()
                        }
                    }
                }
            });

            promises.reduce((prev, promise) => {
                return prev
                  .then(promise)
                  .catch(err => {
                    console.warn('err', err.message);
                  });
              }, Promise.resolve());   
        }
    }

    return (<>
        {
            <ListGroup>
                <ListGroupItem className="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 className="mb-0">Instances</h4>
                        <span>Started: { moment(props.props.createdAt).format("DD/MM/YYYY hh:mm A") } on {labInstance.workerIp} </span>
                    </div>
                    <div>
                    {
                        (!props.props.lab.name.startsWith('Sandbox_')) && 
                        <Button variant="danger" className="ml-2" href={`/labs/${props.props.lab.id}/see/${props.props.id}`}>See Lab</Button>
                    }
                    {(props.user.roles.includes("ROLE_TEACHER") || props.user.roles.includes("ROLE_TEACHER_EDITOR") || props.user.roles.includes("ROLE_ADMINISTRATOR") || props.user.roles.includes("ROLE_SUPER_ADMINISTRATOR")) &&
                        <Button variant="danger" className="ml-2" onClick={() => setShowStopLabModal(true)}>Stop lab</Button>
                    }
                    {
                        <Button variant="danger" className="ml-2" onClick={() => setShowLeaveLabModal(true)} >Leave lab</Button>
                    }
                    {(props.user.roles.includes("ROLE_TEACHER") || props.user.roles.includes("ROLE_TEACHER_EDITOR") || props.user.roles.includes("ROLE_ADMINISTRATOR") || props.user.roles.includes("ROLE_SUPER_ADMINISTRATOR")) &&
                        <input type="checkbox" value={props.props.uuid} name="checkLab" class="ml-4 checkLab"></input>
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
                    <InstanceList instances={labInstance.deviceInstances} lab={props.props.lab} onStateUpdate={onInstanceStateUpdate} showControls={true} user={props.user} allInstancesPage={true}>
                    </InstanceList>
                }
            </ListGroup>
        }
        <Modal show={showLeaveLabModal} onHide={() => setShowLeaveLabModal(false)}>
            <Modal.Header closeButton>
                <Modal.Title>Leave lab</Modal.Title>
            </Modal.Header>
            <Modal.Body>
                If you leave the lab, <strong>all your instances will be deleted and all virtual machines associated will be destroyed.</strong> Are you sure you want to leave this lab ?
            </Modal.Body>
            <Modal.Footer>
                <Button variant="default" onClick={() => setShowLeaveLabModal(false)}>Close</Button>
                <Button variant="danger" onClick={onLeaveLab}>Leave</Button>
            </Modal.Footer>
        </Modal>
        <Modal show={showStopLabModal} onHide={() => setShowStopLabModal(false)}>
            <Modal.Header closeButton>
                <Modal.Title>Stop devices</Modal.Title>
            </Modal.Header>
            <Modal.Body>
                Are you sure you want to stop the devices ?
            </Modal.Body>
            <Modal.Footer>
                <Button variant="default" onClick={() => setShowStopLabModal(false)}>Close</Button>
                <Button variant="danger" onClick={stopDevices}>Stop</Button>
            </Modal.Footer>
        </Modal>
    </>)
}

export default AllInstancesManager;