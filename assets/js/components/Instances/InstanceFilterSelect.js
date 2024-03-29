import React, { useState, useEffect} from 'react';
import Noty from 'noty';
import Remotelabz from '../API';
import FilterInstancesList from './FilterInstancesList';
import {ListGroup, ListGroupItem, Button, Modal} from 'react-bootstrap';
import AllInstancesManager from './AllInstancesManager';

export default function InstanceFilterSelect(props = {labInstances}) {
    const [itemFilter, setItemFilter] = useState([]);
    const [options, setOptions] = useState();
    const [filter, setFilter] = useState("none");
    const [item, setItem] = useState("allInstances");
    const [instances, setInstances] = useState(props.labInstances);
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [showForceLeaveLabModal, setShowForceLeaveLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)
    const [instancesList, setInstancesList] = useState(null)

    let teachers = [];
    let students = [];
    let admins = [];
    let optionsList = [];
    let deviceInstancesToStop = [];

    useEffect(() => {
        setLoadingInstanceState(true)
        refreshInstance()
        const interval = setInterval(refreshInstance, 20000)
        return () => {
            clearInterval(interval)
            setInstances(null)
            setLoadingInstanceState(true)
        }
    }, [filter, item]);

    useEffect(() => {
        console.log(filter);
        if (filter == "group") {
            optionsList = itemFilter.map((group) => (
                <><option
                  key={group.id}
                  value={group.uuid}
                >{group.name}</option></>
              ))
            
              optionsList.unshift(<><option
                key={"0"}
                value ="allGroups"
              >All Groups</option></>);
              setOptions(optionsList);
        }
        else if (filter == "lab") {
            optionsList = itemFilter.map((lab) => (
                <><option
                  key={lab.id}
                  value={lab.uuid}
                >{lab.name}</option></>
              ))
            
              optionsList.unshift(<><option
                key={"0"}
                value ="allLabs"
              >All Labs</option></>);
              setOptions(optionsList);
        }
        else if (filter == "teacher" || filter == "student" || filter == "admin") {
            optionsList = itemFilter.map((user) => (
                <><option
                  key={user.id}
                  value={user.uuid}
                >{user.name}</option></>
              ))

              if (filter == "teacher") {
                optionsList.unshift(<><option
                    key={"0"}
                    value ="allTeachers"
                  >All Teachers</option></>);
              }
              else if (filter == "admin") {
                optionsList.unshift(<><option
                    key={"0"}
                    value ="allAdmins"
                  >All Administrators</option></>);
              }
              else {
                optionsList.unshift(<><option
                    key={"0"}
                    value ="allStudents"
                  >All Students</option></>);
              }
              setOptions(optionsList);
        }
        else if (filter == "none") {
            optionsList = 
                <><option
                  key={"0"}
                  value ="allInstances"
                >All Instances</option></>;
                setOptions(optionsList);   
        }
      }, [filter]);

    function onChange() {
        let filterValue = document.getElementById("instanceSelect").value;
        return new Promise(resolve => {
            if (filterValue == "group") {
                Remotelabz.groups.all()
                .then(response => {
                    setItemFilter(response.data);
                    setFilter(filterValue);
                    setItem("allGroups");
                })
            }
            else if (filterValue == "lab") {
                Remotelabz.labs.all()
                .then(response => {
                    setItemFilter(response.data);
                    setFilter(filterValue);
                    setItem("allLabs");
                })
            }
            else if (filterValue == "teacher" || filterValue == "student" || filterValue == "admin") {
                Remotelabz.users.all()
                .then(response => {
                    const usersList = response.data;
                    for(let user of usersList) {
                        let teacher = false;
                        let admin = false;
                        for(let role of user.roles) {
                            if (role == "ROLE_TEACHER") {
                                teacher = true;
                                break;
                            }
                            if (role == "ROLE_ADMINISTRATOR" || role == "ROLE_SUPER_ADMINISTRATOR") {
                                admin = true;
                                break;
                            }
                        }

                        if (teacher == true) {
                            teachers.push(user);
                        }
                        else if (admin == true) {
                            admins.push(user)
                        }
                        else {
                            students.push(user);
                        }
                    }

                    if (filterValue == "teacher") {
                        setItemFilter(teachers);
                        setItem("allTeachers");
                    }
                    else if (filterValue == "admin") {
                        setItemFilter(admins);
                        setItem("allAdmins");
                    }
                    else {
                        setItemFilter(students);
                        setItem("allStudents");
                    }
                    setFilter(filterValue);
                })
            }
            else if (filterValue == "none"){
                setItem("allInstances");
                setFilter(filterValue);
                
            }
        })
    }

    function refreshInstance() {
        
        let request;
        console.log(filter);
        console.log(item);

        if (item == "allGroups") {
            request = Remotelabz.instances.lab.getOwnedByGroup();  
            console.log("a");  
        }
        else if (filter == "group" && item != "allGroups") {
            request = Remotelabz.instances.lab.getByGroup(item);
            console.log("b"); 
        }
        else if (item == "allLabs") {
            request = Remotelabz.instances.lab.getOrderedByLab();
            console.log("c"); 
        }
        else if (filter == "lab" && item != "allLabs") {
            request = Remotelabz.instances.lab.getByLab(item);
            console.log("g"); 
        }
        else if (item == "allTeachers" || item == "allStudents" || item == "allAdmins") {
            let userType = "";
            if(item == "allTeachers") {
                userType = "teacher"
            }
            else if (item == "allStudents") {
                userType = "student"
            }
            else if (item == "allAdmins") {
                userType = "admin"
            }

            request = Remotelabz.instances.lab.getOwnedByUserType(userType);
            console.log("d"); 
        }
        else if ((filter == "teacher" && item != "allTeachers") || (filter == "student" && item != "allStudents") || (filter == "admin" && item != "allAdmins")) {
            request = Remotelabz.instances.lab.getByUser(item);
            console.log("e"); 
        }
        else if (item == "allInstances"){
            request = Remotelabz.instances.lab.getAll(); 
            console.log("f"); 
        }
        
        request.then(response => {
            setInstances(
                response.data
            )

            console.log(response.data)
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
                        
                        <div className="col"><AllInstancesManager props={labInstance}></AllInstancesManager></div>
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

    /*function getInstances() {
        let itemValue = document.getElementById("itemSelect").value;
        console.log(itemValue);
        return new Promise(resolve => {
            if (itemValue == "allGroups") {
                    Remotelabz.instances.lab.getOwnedByGroup()
                    .then((response) => {
                        setInstances(response.data)
                    })
                    .catch(error => {
                        if (error.response) {
                            if (error.response.status <= 500) {
                                setInstances(undefined)
                            } else {
                                setInstances(undefined)
                                new Noty({
                                    text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                                    type: 'error'
                                }).show()
                            }
                        }
                    })
                setItem(itemValue);
                console.log("1"); 
            }
            else if (filter == "group" && itemValue != "allGroups") {
                console.log("2"); 
                Remotelabz.instances.lab.getByGroup(itemValue)
                .then(response => {
                    if (response == null || response == undefined) {
                        console.log("no instance of this group")
                    }
                    setItem(itemValue);
                    setInstances(response.data);
                    console.log(response.data);
                    
                }).catch(error => {
                    if (error.response) {
                        if (error.response.status <= 500) {
                            setInstances(undefined)
                        } else {
                            setInstances(undefined)
                            new Noty({
                                text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                                type: 'error'
                            }).show()
                        }
                    }
                })
            }
            else if (itemValue == "allLabs") {
                Remotelabz.instances.lab.getOrderedByLab()
                    .then((response) => {
                        setInstances(response.data)
                    })
                    .catch(error => {
                        if (error.response) {
                            if (error.response.status <= 500) {
                                setInstances(undefined)
                            } else {
                                setInstances(undefined)
                                new Noty({
                                    text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                                    type: 'error'
                                }).show()
                            }
                        }
                    })
                console.log("3"); 
                setItem(itemValue);
            }
            else if (filter == "lab" && itemValue != "allLabs") {
                Remotelabz.instances.lab.getByLab(itemValue)
                .then(response => {
                    setItem(itemValue);
                    setInstances(response.data);
                    console.log(response.data);
                    
                })
                .catch(error => {
                    if (error.response) {
                        if (error.response.status <= 500) {
                            setInstances(undefined)
                        } else {
                            setInstances(undefined)
                            new Noty({
                                text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                                type: 'error'
                            }).show()
                        }
                    }
                });
                console.log("g"); 
            }
            else if (itemValue == "allTeachers" || itemValue == "allStudents" || itemValue == "allAdmins") {
                let userType = ""
                if(itemValue == "allTeachers") {
                    userType = "teacher"
                }
                else if (itemValue == "allStudents") {
                    userType = "student"
                }
                else if (itemValue == "allAdmins") {
                    userType = "admin"
                }
                Remotelabz.instances.lab.getOwnedByUserType(userType)
                    .then((response) => {
                        setInstances(response.data)
                    })
                    .catch(error => {
                        if (error.response) {
                            if (error.response.status <= 500) {
                                setInstances(undefined)
                            } else {
                                setInstances(undefined)
                                new Noty({
                                    text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                                    type: 'error'
                                }).show()
                            }
                        }
                    })
                setItem(itemValue);
                console.log("4"); 
                
            }
            else if ((filter == "teacher" && itemValue != "allTeachers") || (filter == "student" && itemValue != "allStudents") || (filter == "admin" && itemValue != "allAdmins")) {
                Remotelabz.instances.lab.getByUser(itemValue)
                .then(response => {
                    setItem(itemValue);
                    setInstances(response.data);
                    console.log(response.data);
                    
                }).catch(error => {
                    if (error.response) {
                        if (error.response.status <= 500) {
                            setInstances(undefined)
                        } else {
                            setInstances(undefined)
                            new Noty({
                                text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                                type: 'error'
                            }).show()
                        }
                    }
                })
                console.log("5"); 
            }
            else if (itemValue == "allInstances"){
                Remotelabz.instances.lab.getAll()
                .then(response => {
                    setItem(itemValue);
                    setInstances(response.data);
                    console.log(response.data);
                }).catch(error => {
                    if (error.response) {
                        if (error.response.status <= 500) {
                            setInstances(undefined)
                        } else {
                            setInstances(undefined)
                            new Noty({
                                text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                                type: 'error'
                            }).show()
                        }
                    }
                })
                console.log("6"); 
            }
        })
    }*/

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
        console.log(force);

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
                    console.log(hasInstancesStillRunning(response.data));
                    if (hasInstancesStillRunning(response.data)) {
                        running = true;
                        for(let deviceInstance of response.data.deviceInstances) {
                            if ((deviceInstance.state != 'stopped') && (deviceInstance.state != 'exported') && (deviceInstance.state != 'error')) {
                                deviceInstancesToStop.push(deviceInstance);
                            }
                        }

                        console.log(running);
    
                        if (running == true) {
                            console.log("modal");
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
                    //setLabInstance({ ...labInstance, state: "deleting" })
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

    return (
        <div>
            <div>
                <div><span>Filter by : </span></div>
                <div className="d-flex align-items-center mb-2">
                <select className='form-control' id="instanceSelect" onChange={onChange}>
                    <option value="none">None</option>
                    <option value="group">Group</option>
                    <option value="lab">Lab</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Administrator</option>
                </select>
                <select className='form-control' id="itemSelect" onChange={refreshInstance}>
                    {options}
                </select>
                </div>
            </div>
            <div className="d-flex justify-content-end mb-2">
                {
                    <Button variant="danger" className="ml-2" onClick={() => setShowLeaveLabModal(true)}>Leave labs</Button>
                }
                <input type="checkbox" value="leaveAll" name="checkAll" id="checkAll" class="ml-4" onClick={checkAll}></input>
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
                    <Button variant="danger" onClick={stopDevices}>Continue</Button>
                </Modal.Footer>
            </Modal>
        </div>
    );
}