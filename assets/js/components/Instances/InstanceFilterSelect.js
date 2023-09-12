import React, { useState, useEffect} from 'react';
import Remotelabz from '../API';
import FilterInstancesList from './FilterInstancesList';

export default function InstanceFilterSelect() {
    const [itemFilter, setItemFilter] = useState([]);
    const [options, setOptions] = useState();
    const [filter, setFilter] = useState("none");
    const [item, setItem] = useState("allInstances");
    const [instances, setInstances] = useState([]);
    let teachers = [];
    let students = [];
    let admins = [];
    let optionsList = [];
    let instancesList = [];

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

      useEffect(() => {
        /*if (instances) {
            console.log(instances);
            console.log(filter);
            console.log(item);
            setInstancesList(<FilterInstancesList
                labInstances={instances} filter={filter} itemValue={item}
            ></FilterInstancesList>);
        }*/
        
        }
      , [instances]);

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

    function getInstances() {
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
    }

    return (
        <div>
        <div className="d-flex align-items-center mb-2">
            <div>Filter by : </div>
            <select className='form-control' id="instanceSelect" onChange={onChange}>
                <option value="none">None</option>
                <option value="group">Group</option>
                <option value="lab">Lab</option>
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
                <option value="admin">Administrator</option>
            </select>
            <select className='form-control' id="itemSelect" onChange={getInstances}>
                {options}
            </select>
            </div>
            {instances != undefined  &&  <FilterInstancesList
                labInstances={instances} filter={filter} itemValue={item} itemFilter={itemFilter}
            ></FilterInstancesList>}
        </div>
    );
}