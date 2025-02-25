import Noty from 'noty';
import Remotelabz from '../API';
import React, { useState, useEffect } from 'react';
import AllInstancesManager from './AllInstancesManager';

function FilterInstancesList(props = {labInstances: [], filter, itemValue, itemFilter}) { 
    const [labInstances, setLabInstances] = useState(props.labInstances)
    const [instancesList, setInstancesList] = useState(null)
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)
    const [filter, setFilter] = useState()
    const [itemValue, setItemValue] = useState()
    const [itemFilter, setItemFilter] = useState()
    //console.log(props.filter);
    //console.log(props.itemValue);

    useEffect(()=> {
        setFilter(props.filter);
        setItemValue(props.itemValue);
        setItemFilter(props.itemFilter)
        //console.log(props.filter);
        //console.log(props.itemValue);
        //console.log(props.itemFilter);
    }, [props.filter, props.itemValue, props.itemFilter])

    useEffect(() => {
        //console.log(props.filter);
        //console.log(props.itemValue);
        //console.log(props.itemFilter);
        setLoadingInstanceState(true)
        refreshInstance()
        const interval = setInterval(refreshInstance, 30000)
        return () => {
            clearInterval(interval)
            setLabInstances(null)
            setLoadingInstanceState(true)
        }
    }, [filter, itemValue])

    function refreshInstance() {
        
        let request;
        //console.log(props.filter);
        //console.log(props.itemValue);

        if (props.itemValue == "allGroups") {
            request = Remotelabz.instances.lab.getOwnedByGroup();  
            //console.log("a");  
        }
        else if (props.filter == "group" && props.itemValue != "allGroups") {
            request = Remotelabz.instances.lab.getByGroup(props.itemValue);
            //console.log("b"); 
        }
        else if (props.itemValue == "allLabs") {
            request = Remotelabz.instances.lab.getOrderedByLab();
            //console.log("c"); 
        }
        else if (props.filter == "lab" && props.itemValue != "allLabs") {
            request = Remotelabz.instances.lab.getByLab(props.itemValue);
            //console.log("g"); 
        }
        else if (props.itemValue == "allTeachers" || props.itemValue == "allStudents" || props.itemValue == "allAdmins") {
            let userType = "";
            if(props.itemValue == "allTeachers") {
                userType = "teacher"
            }
            else if (props.itemValue == "allStudents") {
                userType = "student"
            }
            else if (props.itemValue == "allAdmins") {
                userType = "admin"
            }

            request = Remotelabz.instances.lab.getOwnedByUserType(userType);
            //console.log("d"); 
        }
        else if ((props.filter == "teacher" && props.itemValue != "allTeachers") || (props.filter == "student" && props.itemValue != "allStudents") || (props.filter == "admin" && props.itemValue != "allAdmins")) {
            request = Remotelabz.instances.lab.getByUser(props.itemValue);
            //console.log("e"); 
        }
        else if (props.itemValue == "allInstances"){
            request = Remotelabz.instances.lab.getAll(); 
            //console.log("f"); 
        }
        
        request.then(response => {
            setLabInstances(
                response.data
            )

            //console.log(response.data)
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

    return (<>
        { 
        instancesList
        }  
    </>)
}

export default FilterInstancesList;