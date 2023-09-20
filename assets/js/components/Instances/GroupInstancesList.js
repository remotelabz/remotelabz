import React, { useState, useEffect} from 'react';
import Noty from 'noty';
import Remotelabz from '../API';
import FilterInstancesList from './FilterInstancesList';
import {ListGroup, ListGroupItem, Button, Modal} from 'react-bootstrap';
import AllInstancesManager from './AllInstancesManager';

export default function GroupInstancesList(props = {labInstances, labs, group}) {
    const [options, setOptions] = useState();
    //const [instances, setInstances] = useState();
    const [filter, setFilter] = useState("allLabs");
    //const [labs, setLabs] = useState();
    //const [group, setGroup] = useState();
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)
    const [instancesList, setInstancesList] = useState(null)
    //console.log(props);
    console.log(props.labInstances);
    console.log(props.labs);
    console.log(props.group);
    

    let teachers = [];
    let students = [];
    let admins = [];
    let optionsList = [];
    let deviceInstancesToStop = [];

    /*useEffect(()=> {
        /*setInstances(labInstances);
        setLabs(labs);
        setGroup(props.group)*/
        /*console.log(labInstances);
        console.log(labs);
        console.log(group);
    }, [labInstances, props.labs, props.group])*/

    /*useEffect(() => {
        getLabs()
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
            <><option
                  key={lab.id}
                  value={lab.uuid}
                >{lab.name}</option></>
        });

        optionsList.unshift(<><option
            key={"0"}
            value ="allLabs"
          >All Labs</option></>);

        setOptions(optionsList);

    }, []);

    function getLabs() {
        Remotelabz.instances.lab.getGroupInstances(props.group.slug)
        .then((response)=> {
            setLabs(response.labs);
            console.log(response.labs);
        })
    }

    function onChange() {
        let filterValue = document.getElementById("labSelect").value;
        setFilter(filterValue);
    }

    function refreshInstance() {
        
        let request;

        if(filter == "allLabs") {
            request = Remotelabz.instances.lab.getGroupInstances(props.group.slug);  
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

    return (
        <div>
            <div>
                <div><span>Filter by lab : </span></div>
                <div className="d-flex align-items-center mb-2">
                <select className='form-control' id="labSelect">
                    {options}
                </select>
                </div>
            </div>
        </div>
    );*/

    return (
        <div>
        </div>
    );
}