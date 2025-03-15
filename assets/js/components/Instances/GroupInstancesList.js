import React, { useState, useEffect} from 'react';
import Noty from 'noty';
import Remotelabz from '../API';
import FilterInstancesList from './FilterInstancesList';
import {ListGroup, ListGroupItem, Button, Modal} from 'react-bootstrap';
import AllInstancesManager from './AllInstancesManager';

export default function GroupInstancesList(props = {instances, group, user}) {
    const [options, setOptions] = useState();
    const [instances, setInstances] = useState();
    //const [filter, setFilter] = useState("allLabs");
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [showForceLeaveLabModal, setShowForceLeaveLabModal] = useState(false)
    const [showForceStopModal, setShowForceStopModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)
    const [instancesList, setInstancesList] = useState(null)

    let deviceInstancesToStop = [];

    useEffect(() => {
        setLoadingInstanceState(true)
        refreshInstance()
        const interval = setInterval(refreshInstance, 30000)
        return () => {
            clearInterval(interval)
            setInstances(null)
            setLoadingInstanceState(true)
        }
    }, []);

    /*useEffect(() => {
        let optionsList = props.labs.map((lab) => {
            return(
            <><option
                  key={lab.id}
                  value={lab.uuid}
                >{lab.name}</option></>)
        });

        optionsList.unshift(<><option
            key={"0"}
            value ="allLabs"
          >All Labs</option></>);

        setOptions(optionsList);
    }, []);*/

    useEffect(() => {

        if (instances != undefined && instances !== "") {
            const list = instances.map((labInstance) => {
                return (
                <div className="wrapper align-items-center p-3 border-bottom lab-item" key={labInstance.id} >
                    <div>
                        <div>
                            <a href={`/labs/${labInstance.id}`} className="lab-item-name" title={labInstance.lab.name} data-toggle="tooltip" data-placement="top">
                            </a>
                            Lab&nbsp; {labInstance.lab.name}&nbsp;started by
                            {labInstance !=  null && (labInstance.ownedBy == "user" ? `user ${labInstance.owner.name}` : `group ${labInstance.owner.name}` )}<br/>
                        </div>
                        
                        <div className="col"><AllInstancesManager props={labInstance} user={props.user}></AllInstancesManager></div>
                    </div>
                </div>)
            });
    
            setInstancesList(list);
        }

        if (instances === "") {
            const list = <div class="wrapper align-items-center p-3 border-bottom lab-item">
            <span class="lab-item-name">
                None
            </span>
        </div>

            setInstancesList(list);
        }
        
        
    }, [instances]);

    function onChange() {
        let filterValue = document.getElementById("labSelect").value;
        setFilter(filterValue);
    }

    function getParentPath(group, path) {
        //console.log(path);
        if (group.parent !== undefined) {
            path = getParentPath(group.parent, group.parent.slug) + "/" + path;
            //console.log(path);
        }
        return path;
    }

    function refreshInstance() {
        
        let request;

        let path = getParentPath(props.group, props.group.slug);

        let filter = document.getElementById("group_instance_filter").value;
        let page = document.getElementById("group_instance_page").value;

        request = Remotelabz.instances.lab.getGroupInstances(path, filter, page)

        request.then(response => {
            setInstances(response.data);
            const list = response.data.map((labInstance) => {
                return (
                <div className="wrapper align-items-center p-3 border-bottom lab-item" key={labInstance.id} >
                    <div>
                        <div>
                            <a href={`/labs/${labInstance.id}`} className="lab-item-name" title={labInstance.lab.name} data-toggle="tooltip" data-placement="top">
                            </a>
                            Lab&nbsp; {labInstance.lab.name}&nbsp;started by
                            {labInstance !=  null && (labInstance.ownedBy == "user" ? `user ${labInstance.owner.name}` : `group ${labInstance.owner.name}` )}<br/>
                        </div>
                        
                        <div className="col"><AllInstancesManager props={labInstance} user={props.user}></AllInstancesManager></div>
                    </div>
                </div>)
            });

            setInstancesList(list)

        }).catch(error => {
            if (error.response) {
                if (error.response.status <= 500) {
                    setInstances(null)
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
        return labInstance.deviceInstances.some(i => (i.state != 'stopped') && (i.state != 'exported') && (i.state != 'error'));
    }

    async function onLeaveLab(force) {
        setShowLeaveLabModal(false)
        setShowForceLeaveLabModal(false)
        //setLoadingInstanceState(true)
        const boxes = document.querySelectorAll(".checkLab");
        let instancesToDelete = [];
        let running = false;
        deviceInstancesToStop = [];

        for (var i=0; i<boxes.length; i++) {
            // And stick the checked ones onto an array...
            if (boxes[i].checked) {
                instancesToDelete.push(boxes[i].value);
            }
        }

        if (force == false) {
            for(let instanceToDelete of instancesToDelete) {
                Remotelabz.instances.lab.get(instanceToDelete)
                .then((response) => {
                    if (hasInstancesStillRunning(response.data)) {
                        running = true;
                        for(let deviceInstance of response.data.deviceInstances) {
                            if ((deviceInstance.state != 'stopped') && (deviceInstance.state != 'exported') && (deviceInstance.state != 'error')) {
                                deviceInstancesToStop.push(deviceInstance);
                            }
                        }
    
                        if (running == true) {
                            setShowForceLeaveLabModal(true);
                        }
                    }
                    else {
                        onLeaveLab(true);
                    }
                })
            }
            
        }

        else {

            for(let instanceToDelete of instancesToDelete) {
                try {

                    Remotelabz.instances.lab.delete(instanceToDelete)
                    let newInstances = instances.map((instance)=>{
                        if (instance.uuid == instanceToDelete) {
                            instance.state = "deleting"
                        }
                        return instance;
                    });
                    setInstances(newInstances)
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

    function stopDevices(leave) {
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
        if (leave == true) {
            onLeaveLab(true);
        }
    }

    function stopAllDevices() {
        setShowForceStopModal(false)
        const boxes = document.querySelectorAll(".checkLab");
        let labInstancesToStop = [];
        let running = false;
        deviceInstancesToStop = [];
        let promises = []

        for (var i=0; i<boxes.length; i++) {
            if (boxes[i].checked) {
                labInstancesToStop.push(boxes[i].value);
            }
        }

        for(let labInstanceToStop of labInstancesToStop) {
            promises.push(()=> {return Remotelabz.instances.lab.get(labInstanceToStop)
                .then((response) => {
                    if (hasInstancesStillRunning(response.data)) {
                        running = true;
                        for(let deviceInstance of response.data.deviceInstances) {
                            //console.log(deviceInstance)
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
                stopDevices(false);
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

    return (
        <div>
            {/*<div>
                <div><span>Filter by lab : </span></div>
                <div className="d-flex align-items-center mb-2 mt-2">
                <select className='form-control' id="labSelect" onChange={onChange}>
                    {options}
                </select>
                </div>
            </div>*/}
            <div className="d-flex justify-content-end mb-2">
                {instances !== "" &&
                    <>
                    <Button variant="danger" className="ml-2" onClick={() => setShowForceStopModal(true)}>Stop labs</Button>
                    <Button variant="danger" className="ml-2" onClick={() => setShowLeaveLabModal(true)}>Leave labs</Button>
                    </>
                }
                {instances !== "" &&
                    <input type="checkbox" value="leaveAll" name="checkAll" id="checkAll" class="ml-4" onClick={checkAll}></input>
                }
            </div>
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
                    <Button variant="danger" onClick={() => stopDevices(true)}>Continue</Button>
                </Modal.Footer>
            </Modal>
            <Modal show={showForceStopModal} onHide={() => setShowForceStopModal(false)}>
                <Modal.Header closeButton>
                    <Modal.Title>Force to stop labs</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    Are you sure you want to stop the devices of the selected labs?
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="default" onClick={() => setShowForceStopModal(false)}>Close</Button>
                    <Button variant="danger" onClick={stopAllDevices}>Continue</Button>
                </Modal.Footer>
            </Modal>
        </div>
    );

}