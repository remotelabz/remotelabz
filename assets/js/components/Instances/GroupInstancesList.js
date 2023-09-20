import React, { useState, useEffect} from 'react';
import Noty from 'noty';
import Remotelabz from '../API';
import FilterInstancesList from './FilterInstancesList';
import {ListGroup, ListGroupItem, Button, Modal} from 'react-bootstrap';
import AllInstancesManager from './AllInstancesManager';

export default function GroupInstancesList(props = {labInstances, labs, groups}) {
    const [options, setOptions] = useState();
    const [instances, setInstances] = useState(props.labInstances);
    const [filter, setFilter] = useState("allLabs");
    //const [labs, setLabs] = useState(props.labs);
    //const [group, setGroup] = useState();
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)
    const [instancesList, setInstancesList] = useState(null)

    useEffect(() => {
        setLoadingInstanceState(true)
        refreshInstance()
        const interval = setInterval(refreshInstance, 20000)
        return () => {
            clearInterval(interval)
            setInstances(null)
            setLoadingInstanceState(true)
        }
    }, [filter]);

    useEffect(() => {
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
    }, []);

    function onChange() {
        let filterValue = document.getElementById("labSelect").value;
        setFilter(filterValue);
    }

    function refreshInstance() {
        
        let request;

        console.log(filter);
        if(filter == "allLabs") {
            request = Remotelabz.instances.lab.getGroupInstances(props.group.slug);  
        }
        else {
            request = Remotelabz.instances.lab.getGroupInstancesByLab(props.group.slug, filter)
        }
        request.then(response => {
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

    return (
        <div>
            <div>
                <div><span>Filter by lab : </span></div>
                <div className="d-flex align-items-center mb-2 mt-2">
                <select className='form-control' id="labSelect" onChange={onChange}>
                    {options}
                </select>
                </div>
            </div>
            {instancesList}
        </div>
    );

}