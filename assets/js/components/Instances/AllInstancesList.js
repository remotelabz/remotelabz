import Noty from 'noty';
import Remotelabz from '../API';
import InstanceList from './InstanceList';
import { GroupRoles } from '../Groups/Groups';
import React, { useState, useEffect } from 'react';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import { ListGroup, ListGroupItem, Button, Modal, Spinner } from 'react-bootstrap';
import moment from 'moment/moment';
import AllInstancesManager from './AllInstancesManager';

function AllInstancesList(props = {labInstances: []}) { 
    const [labInstances, setLabInstances] = useState(props)
    const [instancesList, setInstancesList] = useState(null)
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)
    //const [viewAs, setViewAs] = useState({ type: props.labInstance.ownedBy, uuid: props.labInstance.owner.uuid, value: props.labInstance.owner.id, label: props.labInstance.owner.name })

    useEffect(() => {
        setLoadingInstanceState(true)
        refreshInstance()
        const interval = setInterval(refreshInstance, 20000)
        return () => {
            clearInterval(interval)
            setLabInstances(null)
            setLoadingInstanceState(true)
        }
    }, [])

    function refreshInstance() {
        
        let request

        //if (viewAs.type === 'user') {
            request = Remotelabz.instances.lab.getAll();
        /*} else {
            request = Remotelabz.instances.lab.getByLabAndGroup(props.labInstance.lab.uuid, viewAs.uuid)
        }*/
        
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


    /*function hasInstancesStillRunning() {
        //return labInstance.deviceInstances.some(i => (i.state != 'stopped') && (i.state != 'exported') && (i.state != 'error'));
        return false;
    }

    async function onInstanceStateUpdate() {
        // const response = await Remotelabz.instances.get(labInstance.uuid, 'lab')
        // setLabInstance(response.data)
    }*/

    /*async function onLeaveLab() {
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
    }*/

    return (<>
        { 
        instancesList
        }  
    </>)
}

export default AllInstancesList;