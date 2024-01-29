import Noty from 'noty';
import Remotelabz from '../API';
import InstanceList from './InstanceList';
import { GroupRoles } from '../Groups/Groups';
import React, { useState, useEffect } from 'react';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import { ListGroup, ListGroupItem, Button, Modal, Spinner } from 'react-bootstrap';
import moment from 'moment/moment';
import AllInstancesManager from './AllInstancesManager';

function AllInstancesList(props = {labInstances: [], user:{}}) { 
    const [labInstances, setLabInstances] = useState(props)
    const [instancesList, setInstancesList] = useState(null)
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [showForceLeaveLabModal, setShowForceLeaveLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)

    let deviceInstancesToStop = [];

    useEffect(() => {
        setLoadingInstanceState(true)
        refreshInstance()
        const interval = setInterval(refreshInstance, 10000)
        return () => {
            clearInterval(interval)
            setLabInstances(null)
            setLoadingInstanceState(true)
        }
    }, [])

    
    function refreshInstance() {
        
        let request
        let filter = document.getElementById("instance_filter").value;
        let subFilter = document.getElementById("instance_subFilter").value;
        let page = document.getElementById("instance_page").value;

        request = Remotelabz.instances.lab.getAll(filter, subFilter, page);
    
        
        request.then(response => {
            setLabInstances(
                response.data
            )

            const list = response.data.map((labInstance) => {
                return (
                <div className="wrapper align-items-center p-3 border-bottom lab-item" key={labInstance.id} >
                    <div>
                        <div>
                            <a href={`/labs/${labInstance.id}`} className="lab-item-name" title={labInstance.lab.name} data-toggle="tooltip" data-placement="top">
                            </a>
                            Lab&nbsp; {labInstance.lab.name}&nbsp;started by
                            {labInstance !=  null && (labInstance.ownedBy == "user" ? ` user ${labInstance.owner.name}` : 
                            labInstance.ownedBy == "guest" ? ` guest ${labInstance.owner.mail}` : ` group ${labInstance.owner.name}` )}<br/>
                        </div>
                        
                        <div className="col"><AllInstancesManager props={labInstance} user={props.user}></AllInstancesManager></div>
                    </div>
                </div>)
            });

            setInstancesList(list)

        }).catch(error => {
            if (error.response) {
                if (error.response.status <= 500) {
                    setLabInstances(null)
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

    function checkAll() {
        const boxes = document.querySelectorAll(".checkLab");
        let checkAll = document.getElementById("checkAll");

        if (checkAll.checked == true) {
            for(let box of boxes) {
                box.checked = true
            }
        }
        else {
            for(let box of boxes) {
                box.checked = false
            }
        }
    }

    function hasInstancesStillRunning(labInstance) {
        return labInstance.deviceInstances.some(i => (i.state != 'stopped') && (i.state != 'exported') && (i.state != 'error'));    }


    async function onLeaveLab(force) {
        setShowLeaveLabModal(false)
        setShowForceLeaveLabModal(false)
        //setLoadingInstanceState(true)
        const boxes = document.querySelectorAll(".checkLab");
        let instancesToDelete = [];
        let running = false;
        deviceInstancesToStop = [];
        let promises = []

        for (var i=0; i<boxes.length; i++) {
            // And stick the checked ones onto an array...
            if (boxes[i].checked) {
                instancesToDelete.push(boxes[i].value);
            }
        }

        if (force == false) {
            for(let instanceToDelete of instancesToDelete) {
                promises.push(()=> {return Remotelabz.instances.lab.get(instanceToDelete)
                    .then((response) => {
                        if (hasInstancesStillRunning(response.data)) {
                            running = true;
                            for(let deviceInstance of response.data.deviceInstances) {
                                if ((deviceInstance.state != 'stopped') && (deviceInstance.state != 'exported') && (deviceInstance.state != 'error') && (deviceInstance.state != 'reset')) {
                                    deviceInstancesToStop.push(deviceInstance);
                                }
                            }  
                            
                        }                    
                    })
                })
            }
            promises.push(()=>{
                if (running == true) {
                    setShowForceLeaveLabModal(true);
                }
                else {
                    onLeaveLab(true);
                }
            })
            promises.reduce((prev, promise) => {
                return prev
                  .then(promise)
                  .catch(err => {
                    console.warn('err', err.message);
                  });
              }, Promise.resolve());         
            
        }

        else {

            for(let instanceToDelete of instancesToDelete) {
                try {
                    Remotelabz.instances.lab.delete(instanceToDelete)
                    setLabInstances(labInstances.map((instance)=> {
                        if (instance.uuid == instanceToDelete) {
                            instance.state = "deleting"
                        }
                        return instance
                    }))
                } catch (error) {
                    console.error(error)
                    new Noty({
                        text: 'An error happened while leaving the lab. Please try again later.',
                        type: 'error'
                    }).show()
                    //setLoadingInstanceState(false)
                }
            }
        }
    
    }

    function stopDevices() {
        for(let deviceInstanceToStop of deviceInstancesToStop) {
            try {
                Remotelabz.instances.device.stop(deviceInstanceToStop.uuid)
                //setLabInstance({ ...labInstance, state: "deleting" })
            } catch (error) {
                console.error(error)
                new Noty({
                    text: 'An error happened while stopping a device. Please try again later.',
                    type: 'error'
                }).show()
                //setLoadingInstanceState(false)
            }
        }
        onLeaveLab(true);
    }

    return (<>
        {!instancesList && 
        <div class="wrapper align-items-center p-3 border-bottom lab-item">
            <span class="lab-item-name">
                None
            </span>
        </div>}
        {instancesList && (props.user.roles.includes('ROLE_TEACHER') || props.user.roles.includes('ROLE_ADMINISTRATOR') || props.user.roles.includes('ROLE_SUPER_ADMINISTRATOR')) &&
        <div className="d-flex justify-content-end mb-2">
            <Button variant="danger" className="ml-2" onClick={() => setShowLeaveLabModal(true)}>Leave labs</Button>
            <input type="checkbox" value="leaveAll" name="checkAll" id="checkAll" class="ml-4" onClick={checkAll}></input>
        </div>}
        {instancesList}  
        <Modal show={showLeaveLabModal} onHide={() => setShowLeaveLabModal(false)}>
                <Modal.Header closeButton>
                    <Modal.Title>Leave labs</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    If you leave these labs, <strong>all your instances will be deleted and all virtual machines associated will be destroyed.</strong> Are you sure you want to leave these labs ?
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="default" onClick={() => setShowLeaveLabModal(false)}>Close</Button>
                    <Button variant="danger" onClick={() => onLeaveLab(false)}>Leave</Button>
                </Modal.Footer>
            </Modal>

            <Modal show={showForceLeaveLabModal} onHide={() => setShowForceLeaveLabModal(false)}>
                <Modal.Header closeButton>
                    <Modal.Title>Force to Leave labs</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    Some instances still have running devices. These devices will be stopped. Do you still want to continue ?
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="default" onClick={() => setShowForceLeaveLabModal(false)}>Close</Button>
                    <Button variant="danger" onClick={stopDevices}>Continue</Button>
                </Modal.Footer>
            </Modal>
    </>)
}

export default AllInstancesList;