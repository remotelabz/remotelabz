import React, { useState, useEffect} from 'react';
import Noty from 'noty';
import Remotelabz from '../API';
import FilterInstancesList from './FilterInstancesList';
import {ListGroup, ListGroupItem, Button, Modal} from 'react-bootstrap';
import AllInstancesManager from './AllInstancesManager';

export default function InstanceFilterSelect(props) {
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
        const interval = setInterval(refreshInstance, 30000)
        return () => {
            clearInterval(interval)
            setInstances(null)
            setLoadingInstanceState(true)
        }
    }, [filter, item]);

    useEffect(() => {
        refreshInstance();
    }, [item]);

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
                            {labInstance !=  null && (labInstance.ownedBy == "user" ? ` user ${labInstance.owner.name}` :
                            labInstance.ownedBy == "guest" ? ` guest ${labInstance.owner.mail}` :  ` group ${labInstance.owner.name}` )}<br/>
                        </div>
                        <div className="col"><AllInstancesManager props={labInstance} user={props.user} ></AllInstancesManager></div>
                    </div>
                </div>)
            });
    
            setInstancesList(list)
        }
        if (instances === "") {
            const list = <div class="wrapper align-items-center p-3 border-bottom lab-item">
                            <span class="lab-item-name">
                                None
                            </span>
                        </div>

            setInstancesList(list)
        }
        
    }, [instances]);

    useEffect(() => {
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
                if (props.user.roles.includes('ROLE_ADMINISTRATOR') || props.user.roles.includes('ROLE_SUPER_ADMINISTRATOR'))
                {
                    Remotelabz.groups.all()
                    .then(response => {
                        setItemFilter(response.data);
                        setFilter(filterValue);
                        setItem("allGroups");
                    })
                }
                else {
                    setItemFilter(props.user.groups);
                    setFilter(filterValue);
                    setItem("allGroups");
                }
                
            }
            else if (filterValue == "lab") {
                if (props.user.roles.includes('ROLE_ADMINISTRATOR') || props.user.roles.includes('ROLE_SUPER_ADMINISTRATOR'))
                {
                    Remotelabz.labs.all()
                    .then(response => {
                        setItemFilter(response.data);
                        setFilter(filterValue);
                        setItem("allLabs");
                    })
                }
                else {
                    Remotelabz.labs.getByTeacher(props.user.id)
                    .then(response => {
                        setItemFilter(response.data);
                        setFilter(filterValue);
                        setItem("allLabs");
                    })
                }
            }
            else if (filterValue == "teacher" || filterValue == "student" || filterValue == "admin") {

                if (props.user.roles.includes("ROLE_ADMINISTRATOR") || props.user.roles.includes("ROLE_SUPER_ADMINISTRATOR")) {
                    Remotelabz.users.fetchAll()
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
                else {
                    if (filterValue == "teacher") {
                        Remotelabz.users.fetchUserTypeByGroupOwner("teachers", props.user.id)
                        .then(response => {
                            setItemFilter(response.data);
                            setItem("allTeachers");
                            setFilter(filterValue);
                        }).catch(()=>{
                            setItemFilter([]);
                            setItem("allTeachers");
                            setFilter(filterValue);
                        })
                    }
                    else {
                        Remotelabz.users.fetchUserTypeByGroupOwner("students", props.user.id)
                        .then(response => {
                            setItemFilter(response.data);
                            setItem("allStudents");
                            setFilter(filterValue);
                        }).catch(()=>{
                            setItemFilter([]);
                            setItem("allStudents");
                            setFilter(filterValue);
                        })
                    }
                }
                
            }
            else if (filterValue == "none"){
                setItem("allInstances");
                setFilter(filterValue);
                
            }
        })
    }
    function changeItem() {
        let itemValue = document.getElementById("itemSelect").value;
        setItem(itemValue);
        //refreshInstance();
    }

    function refreshInstance() {
        
        let request;

        if (item == "allGroups") {
            request = Remotelabz.instances.lab.getByGroups();    
        }
        else if (filter == "group" && item != "allGroups") {
            request = Remotelabz.instances.lab.getByGroup(item);
        }
        else if (item == "allLabs") {
            request = Remotelabz.instances.lab.getOrderedByLab();
        }
        else if (filter == "lab" && item != "allLabs") {
            request = Remotelabz.instances.lab.getByLab(item);
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
        }
        else if ((filter == "teacher" && item != "allTeachers") || (filter == "student" && item != "allStudents") || (filter == "admin" && item != "allAdmins")) {
            request = Remotelabz.instances.lab.getByUser(item);
        }
        else if (item == "allInstances"){
            request = Remotelabz.instances.lab.getAll(); 
        }
        
        request.then(response => {
            setInstances(
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
                            labInstance.ownedBy == "guest" ? ` guest ${labInstance.owner.mail}` :  ` group ${labInstance.owner.name}` )}<br/>
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
                    const list = <div class="wrapper align-items-center p-3 border-bottom lab-item">
                            <span class="lab-item-name">
                                None
                            </span>
                        </div>

                    setInstancesList(list)
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
                    setInstances(instances.map((instance)=> {
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

    return (
        <div>
            {(props.user.roles.includes('ROLE_TEACHER') || props.user.roles.includes('ROLE_ADMINISTRATOR') || props.user.roles.includes('ROLE_SUPER_ADMINISTRATOR')) &&
            <><div>
                <div><span>Filter by : </span></div>
                <div className="d-flex align-items-center mb-2">
                <select className='form-control' id="instanceSelect" onChange={onChange}>
                    <option value="none">None</option>
                    <option value="group">Group</option>
                    <option value="lab">Lab</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    {
                        (!props.user.roles.includes('ROLE_TEACHER')) &&
                        <option value="admin">Administrator</option>
                    }  
                </select>
                <select className='form-control' id="itemSelect" onChange={changeItem}>
                    {options}
                </select>
                </div>
            </div>
            <div className="d-flex justify-content-end mb-2">
                {instances &&
                    <Button variant="danger" className="ml-2" onClick={() => setShowLeaveLabModal(true)}>Leave labs</Button>
                }
                {instances &&
                    <input type="checkbox" value="leaveAll" name="checkAll" id="checkAll" class="ml-4" onClick={checkAll}></input>
                }
            </div></>}
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