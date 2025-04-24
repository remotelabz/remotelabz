import Noty from 'noty';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import InstanceList from './InstanceList';
import { GroupRoles } from '../Groups/Groups';
import React, { useState, useEffect, useRef } from 'react';
import InstanceOwnerSelect from './InstanceOwnerSelect';
import JitsiCallButton from '../JitsiCall/JitsiCallButton';
import { ListGroup, ListGroupItem, Button, Modal, Spinner } from 'react-bootstrap';
import moment from 'moment/moment';

function InstanceManager(props = {lab: {}, user: {}, labInstance: {}, isJitsiCallEnabled: false, isSandbox: false, hasBooking: false}) {
    const [labInstance, setLabInstance] = useState(props.labInstance);
    const [showLeaveLabModal, setShowLeaveLabModal] = useState(false);
    const [isLoadingInstanceState, setLoadingInstanceState] = useState(false);
    const [viewAs, setViewAs] = useState({ type: props.user.code ? 'guest' : 'user', uuid: props.user.uuid, value: props.user.id, label: props.user.name });
    const [timerCountDown, setTimerCountDown] = useState("");
    const isSandbox = props.isSandbox;
    const timerRef = useRef(null);

    useEffect(() => {
        setLoadingInstanceState(false);
        refreshInstance();
        const interval = setInterval(refreshInstance, 10000);

        return () => {
            clearInterval(interval);
            setLabInstance(null);
            setLoadingInstanceState(true);
        };
    }, [viewAs]);

    useEffect(() => {
        if (props.lab.hasTimer === true) {
            clearInterval(timerRef.current);
            countdown();
        }
    }, [labInstance]);

    function countdown() {
        if (labInstance) {
            const timerEnd = new Date(labInstance.timerEnd).getTime();
            timerRef.current = setInterval(function () {
                const now = new Date().getTime();
                const timeInterval = timerEnd - now;

                let hours = Math.floor((timeInterval % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                let minutes = Math.floor((timeInterval % (1000 * 60 * 60)) / (1000 * 60));
                let seconds = Math.floor((timeInterval % (1000 * 60)) / 1000);

                hours = hours.toString().padStart(2, '0');
                minutes = minutes.toString().padStart(2, '0');
                seconds = seconds.toString().padStart(2, '0');

                const intervalResult = `Timer: ${hours}:${minutes}:${seconds}`;
                setTimerCountDown(intervalResult);

                if (timeInterval < 0) {
                    clearInterval(timerRef.current);
                    setTimerCountDown('Timer: STOPPED');
                    stopDevices();
                }
            }, 1000);
        }
    }

    function stopDevices() {
        for (let deviceInstance of labInstance.deviceInstances) {
            if (deviceInstance.state !== 'stopped') {
                try {
                    Remotelabz.instances.device.stop(deviceInstance.uuid);
                } catch (error) {
                    console.error(error);
                    new Noty({
                        text: 'An error happened while stopping a device. Please try again later.',
                        type: 'error'
                    }).show();
                }
            }
        }
        clearInterval(timerRef.current);
    }

    function refreshInstance() {
        let request;
        if (viewAs.type === 'user') {
            request = Remotelabz.instances.lab.getByLabAndUser(props.lab.uuid, viewAs.uuid);
        } else if (viewAs.type === 'guest') {
            request = Remotelabz.instances.lab.getByLabAndGuest(props.lab.uuid, viewAs.uuid);
        } else {
            request = Remotelabz.instances.lab.getByLabAndGroup(props.lab.uuid, viewAs.uuid);
        }

        request.then(response => {
            setLabInstance({
                ...response.data,
                deviceInstances: response.data.deviceInstances
            });
        }).catch(error => {
            if (error.response) {
                if (error.response.status <= 500) {
                    setLabInstance(null);
                    setLoadingInstanceState(false);
                } else {
                    new Noty({
                        text: 'An error happened while fetching instance state. If this error persist, please contact an administrator.',
                        type: 'error'
                    }).show();
                }
            }
        });
    }

    function isGroupElevatedRole(role) {
        return role === GroupRoles.Owner || role === GroupRoles.Admin;
    }

    function isCurrentUserGroupAdmin(group) {
        if (group.type === 'user') return true;
        if (props.user.code) return true;

        const _group = props.user.groups.find(g => g.uuid === group.uuid);
        return _group ? (_group.role === 'admin' || _group.role === 'owner') : false;
    }

    function isOwnedByGroup() {
        return labInstance.ownedBy === "group";
    }

    function hasInstancesStillRunning() {
        return labInstance.deviceInstances.some(i => (i.state !== 'stopped' && i.state !== 'exported' && i.state !== 'error'));
    }

    async function onInstanceStateUpdate() {
        // Refresh instance if needed
    }

    function onViewAsChange(option) {
        setViewAs(option);
    }

    async function onJoinLab() {
        setLoadingInstanceState(true);
        try {
            const response = await Remotelabz.instances.lab.create(props.lab.uuid, viewAs.uuid, viewAs.type, false);
            setLabInstance(response.data);
            if (!isSandbox) {
                $.ajax({
                    type: "POST",
                    url: `/api/editButton/display`,
                    data: JSON.stringify({ user: props.user, lab: props.lab, labInstance: response.data }),
                    dataType: "json",
                    success: function (response) {
                        $("#instanceButtons").html(response.data.html);
                    }
                });
            }
        } catch (error) {
            if (error.response?.data?.message.includes("No worker available")) {
                new Noty({
                    text: 'No worker available - Please contact an administrator',
                    type: 'error',
                    timeout: 10000
                }).show();
            } else {
                new Noty({
                    text: 'There was an error creating an instance. Please try again later.',
                    type: 'error'
                }).show();
            }
            setLoadingInstanceState(false);
        }
    }

    async function onLeaveLab() {
        setShowLeaveLabModal(false);
        setLoadingInstanceState(true);
        clearInterval(timerRef.current);
        try {
            await Remotelabz.instances.lab.delete(labInstance.uuid);
            setLabInstance({ ...labInstance, state: "deleting" });
            if (!isSandbox) {
                $.ajax({
                    type: "POST",
                    url: `/api/editButton/display`,
                    data: JSON.stringify({ user: props.user, lab: props.lab, labInstance: null }),
                    dataType: "json",
                    success: function (response) {
                        $("#instanceButtons").html(response.data.html);
                    }
                });
            }
            if (isSandbox) {
                setTimeout(() => { window.location.href = "/admin/sandbox"; }, 1500);
            }
        } catch (error) {
            console.error(error);
            new Noty({
                text: error.response?.data?.message?.includes("Worker") ? error.response.data.message : 'An error happened while leaving the lab. Please try again later.',
                type: 'error'
            }).show();
            setLoadingInstanceState(false);
        }
    }

    function onJitsiCallStarted() {
        setLabInstance(prev => ({
            ...prev,
            jitsiCall: { ...prev.jitsiCall, state: 'started' }
        }));
    }


useEffect(() => {
    if (labInstance?.deviceInstances) {
        console.log("Instances depuis InstanceManager :", labInstance.deviceInstances);
    }
}, [labInstance]);


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
console.log("test de InstanceManager");
export default InstanceManager;
