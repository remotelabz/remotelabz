import Noty from 'noty';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import InstanceList from './InstanceList';
import { GroupRoles } from '../Groups/Groups';
import React, { useState, useEffect } from 'react';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import JitsiCallButton from '../JitsiCall/JitsiCallButton';
import { ListGroup, ListGroupItem, Button, Modal, Spinner } from 'react-bootstrap';
import moment from 'moment/moment';

function InstanceManager(props = {lab: {}, user: {}, labInstance: {}, isJitsiCallEnabled: false, isSandbox: false, hasBooking: false}) { 
    const [labInstance, setLabInstance] = useState(props.labInstance)
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false)
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false)
    const [viewAs, setViewAs] = useState({ type: props.user.code ? 'guest' : 'user', uuid: props.user.uuid, value: props.user.id, label: props.user.name })
    const [timerCountDown, setTimerCountDown] = useState("");
    const isSandbox=props.isSandbox

    useEffect(() => {
        setLoadingInstanceState(false)
        refreshInstance()
        const interval = setInterval(refreshInstance, 5000)
        
        return () => {
            clearInterval(interval)
            setLabInstance(null)
            setLoadingInstanceState(true)
        }

    }, [viewAs])

    useEffect(()=> {
        if (props.lab.hasTimer == true) {
            countdown()
        }
    }, [labInstance]);

    function countdown() {
        if (labInstance) {
            var timerEnd = new Date(labInstance.timerEnd).getTime();
            const timer = setInterval(function () {
            var now = new Date().getTime();
            var timeInterval = timerEnd - now;

            var hours = Math.floor((timeInterval % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((timeInterval % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((timeInterval % (1000 * 60)) / 1000);

            if (hours.toString().length == 1) {
                hours = '0'+hours
            }
            if (minutes.toString().length <= 1) {
                minutes = '0'+minutes
            }
            if (seconds.toString().length <= 1) {
                seconds = '0'+seconds
            }
            let intervalResult = 'Timer: '+ hours+':'+ minutes+':'+seconds;
            setTimerCountDown(intervalResult);
            if (timeInterval < 0) {
                clearInterval(timer);
                setTimerCountDown('Timer: STOPPED');
                stopDevices()
            }
            },1000)
        }
    }

    function stopDevices() {

        for(let deviceInstance of labInstance.deviceInstances) {
            if (deviceInstance.state != 'stopped') {
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
    }

    function refreshInstance() {
        let request

        if (viewAs.type === 'user') {
           request = Remotelabz.instances.lab.getByLabAndUser(props.lab.uuid, viewAs.uuid)
        } 
        else if (viewAs.type === 'guest') {
            request = Remotelabz.instances.lab.getByLabAndGuest(props.lab.uuid, viewAs.uuid)
         }
        else {
           request = Remotelabz.instances.lab.getByLabAndGroup(props.lab.uuid, viewAs.uuid)
        }

        request.then(response => {

            setLabInstance({
                ...response.data,
                deviceInstances: response.data.deviceInstances
            })
            /*let promises = []
            for (const deviceInstance of response.data.deviceInstances) {
                promises.push(Remotelabz.instances.get(deviceInstance.uuid));
            }

            Promise.all(promises).then(responses => {
                setLabInstance({
                    ...response.data,
                    deviceInstances: responses.map(response => response.data)
                })
                setLoadingInstanceState(false)
            }).catch(error => {
                new Noty({
                    text: 'An error happened while fetching instances state. Please try refreshing this page. If this error persist, please contact an administrator.',
                    type: 'error'
                }).show()
            })*/
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

    function isGroupElevatedRole(role) {
        //Chercher le rôle du user dans le groupe
        
        return role === GroupRoles.Owner || role === GroupRoles.Admin
    }

    function isCurrentUserGroupAdmin(group) {
        if (group.type === 'user') {
            return true
        }

        if (props.user.code) {
            return true
        }
  
        const _group = props.user.groups.find(g => g.uuid === group.uuid);

        return _group ? (_group.role == 'admin' || _group.role == 'owner') : false
    }

    function isOwnedByGroup() {
        return labInstance.ownedBy == "group"
    }

    function hasInstancesStillRunning() {
        return labInstance.deviceInstances.some(i => (i.state != 'stopped') && (i.state != 'exported') && (i.state != 'error'));    }

    async function onInstanceStateUpdate() {
        // const response = await Remotelabz.instances.get(labInstance.uuid, 'lab')
        // setLabInstance(response.data)
    }

    function onViewAsChange(option) {
        
        setViewAs(option);
    }

    async function onJoinLab() {
        setLoadingInstanceState(true)

        try {
            const response = await Remotelabz.instances.lab.create(props.lab.uuid, viewAs.uuid, viewAs.type, false)
            setLoadingInstanceState(false)
            setLabInstance(response.data)
            if (!isSandbox) {
                $.ajax({
                    type: "POST",
                    url: `/api/editButton/display`,
                    data: JSON.stringify({
                        'user': props.user,
                        'lab': props.lab,
                        'labInstance': response.data
                    }),
                    dataType:"json",
                    success: function (response) {
                        $("#instanceButtons").html(response.data.html);              
                    }  
                });  
            }
        } catch (error) {
            //console.error(error)
            if (error.response.data.message.includes("No worker available")) {
                new Noty({
                    text: 'No worker available - Please contact an administrator',
                    type: 'error',
                    timeout: 10000
                }).show()
            }
            else {
                new Noty({
                    text: 'There was an error creating an instance. Please try again later.',
                    type: 'error'
                }).show()
            }
            setLoadingInstanceState(false)
        }
    }

    async function onLeaveLab() {
        setShowLeaveLabModal(false)
        setLoadingInstanceState(true)

        try {
            await Remotelabz.instances.lab.delete(labInstance.uuid)
            setLabInstance({ ...labInstance, state: "deleting" })
            if (!isSandbox) {
                $.ajax({
                    type: "POST",
                    url: `/api/editButton/display`,
                    data: JSON.stringify({
                        'user': props.user,
                        'lab': props.lab,
                        'labInstance': null
                    }),
                    dataType:"json",
                    success: function (response) {
                        $("#instanceButtons").html(response.data.html);    
                                
                    }  
                }); 
            }
            if(isSandbox) {
                setTimeout(function() {window.location.href="/admin/sandbox"}, 1500);
            }  
            
        } catch (error) {
            console.error(error)
            if (error.response.data.message.includes("Worker") && error.response.data.message.includes("is suspended")) {
                new Noty({
                    text: error.response.data.message,
                    type: 'error'
                }).show()
            }
            else {
                new Noty({
                    text: 'An error happened while leaving the lab. Please try again later.',
                    type: 'error'
                }).show()
            }
            setLoadingInstanceState(false)
        }
        
    }

    function onJitsiCallStarted() {
        let labInstance = {...labInstance};
        labInstance.jitsiCall.state = 'started';
        setLabInstance(labInstance)
    }

    return (<>
        {!isSandbox && props.user.name && 
            <div className="d-flex align-items-center mb-2">
                <div>View as : </div>
                <div className="flex-grow-1 ml-2">
                    <InstanceOwnerSelect
                    user={props.user.id}
                    onChange={onViewAsChange}
                    isDisabled={isLoadingInstanceState}
                    value={viewAs}
                    />
                </div>
            </div>
        }

        {labInstance ?
            <ListGroup>
                <ListGroupItem className="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 className="mb-0">Instances</h4>
                        <span>Started: { moment(labInstance.createdAt).format("DD/MM/YYYY hh:mm A") }</span>
                    </div>
                    <div>
                        {props.lab.virtuality == 1 && props.lab.hasTimer == true && <span id="timer">{timerCountDown}</span>
                        }
                        {props.lab.virtuality == 1 && props.lab.hasTimer == false && <span>No timer</span>
                        }
                    </div>
                    <div>
                    {labInstance.state === "created" &&
                        <Button href="/profile/vpn" variant="primary">
                            <SVG name="download" className="v-sub image-sm"></SVG>
                            <span className="ml-1">OpenVPN file</span>
                        </Button>
                    }
                    {(props.isJitsiCallEnabled && isOwnedByGroup()) &&
                        <JitsiCallButton
                            className="mr-2"
                            isOwnedByGroup={isOwnedByGroup()}
                            isCurrentUserGroupAdmin={isCurrentUserGroupAdmin(viewAs)}
                            onStartCall={onJitsiCallStarted}
                        />
                    }
                    {
                        (!props.lab.name.startsWith('Sandbox_')) && labInstance.state === "created" && (viewAs.type === "user" || viewAs.type === "group") &&
                        <Button variant="danger" className="ml-2" href={`/labs/${props.lab.id}/see/${labInstance.id}`}>See Lab</Button>
                    }
                    {
                        (!props.lab.name.startsWith('Sandbox_')) && labInstance.state === "created" && viewAs.type === "guest" &&
                        <Button variant="danger" className="ml-2" href={`/labs/guest/${props.lab.id}/see/${labInstance.id}`}>See Lab</Button>
                    }
                    {isCurrentUserGroupAdmin(viewAs) &&
                        <Button variant="danger" className="ml-2" onClick={() => setShowLeaveLabModal(true)} disabled={hasInstancesStillRunning() || labInstance.state === "creating" || labInstance.state === "deleting"}>Leave lab</Button>
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
                    <InstanceList instances={labInstance.deviceInstances} labInstance={labInstance} isSandbox={isSandbox} lab={props.lab} onStateUpdate={onInstanceStateUpdate} showControls={isCurrentUserGroupAdmin(viewAs)} user={props.user} allInstancesPage={false}>
                    </InstanceList>
                }
                {labInstance.state === "exporting" &&
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        <Spinner animation="border" size="lg" className="text-muted" />

                        <div className="mt-3">
                            Exporting your instance... This operation may take a moment.
                        </div>
                    </ListGroupItem>
                }
            </ListGroup>
            :
            <ListGroup>
                {isLoadingInstanceState ?
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        <div className="my-3">
                            <div className="dot-bricks"></div>
                        </div>
                        Loading...
                    </ListGroupItem>
                    :
                    
                    <ListGroupItem className="d-flex align-items-center justify-content-center flex-column">
                        {props.lab.virtuality == 1 || (props.lab.virtuality == 0 && props.hasBooking.uuid == viewAs.uuid && props.hasBooking.type == viewAs.type)?
                        
                            (viewAs.type === 'user' || viewAs.type === 'guest' ?
                                <div className="d-flex align-items-center justify-content-center flex-column">
                                    You haven&apos;t joined this lab yet.

                                    <div className="mt-3">
                                        <Button onClick={onJoinLab} disabled={isLoadingInstanceState}>Join this lab</Button>
                                    </div>
                                </div>
                                :
                                <div className="d-flex align-items-center justify-content-center flex-column">
                                    This group hasn&apos;t joined this lab yet.

                                    {isCurrentUserGroupAdmin(viewAs) &&
                                        <div className="mt-3">
                                            <Button onClick={onJoinLab} disabled={isLoadingInstanceState}>Join this lab</Button>
                                        </div>
                                    }
                                </div>
                            )
                        : 
                            <div className="d-flex align-items-center justify-content-center flex-column">
                                You can&apos;t join this lab yet.
                            </div>
                        }
                    </ListGroupItem>
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
    </>)
}

export default InstanceManager;