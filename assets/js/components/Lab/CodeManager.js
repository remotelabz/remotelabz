import React, { useState, useEffect } from 'react';
import Remotelabz from '../API';
import moment from 'moment/moment';
import { Button, Modal} from 'react-bootstrap';
import Noty from 'noty';

export default function CodeManager(props = {lab}) {
    const [invitationCodes, setInvitationCodes] = useState();
    const [loadingInstanceState, setLoadingInstanceState] = useState();
    const [codeList, setCodeList] = useState();
    const [showDeleteCodeModal, setShowDeleteCodeModal] = useState(false);
    const [itemToDelete, setItemToDelete] = useState(null);

    useEffect(() => {
        setLoadingInstanceState(true)
        refreshInstance()
        const interval = setInterval(refreshInstance, 60000)
        return () => {
            clearInterval(interval)
            setLoadingInstanceState(true)
        }
    }, [])

    function deleteCode(uuid) {
        Remotelabz.instances.lab.getByLabAndGuest(props.lab.uuid, uuid)
            .then((response)=> {
                let promises = [];
                for(let deviceInstance of response.data.deviceInstances) {
                    promises.push(Remotelabz.instances.device.stop(deviceInstance.uuid))
                }
                var instance = response.data
                Promise.all(promises).then(()=>{
                    Remotelabz.instances.lab.delete(instance.uuid)
                    .then((response) => {
                        setTimeout(function(){
                            Remotelabz.invitationCode.delete(uuid).then(()=> {
                                setItemToDelete(null);
                                setShowDeleteCodeModal(false);
                                refreshInstance();
                            })
                            .catch((error)=>{
                                new Noty({
                                    text: 'An error happened while deleting code. If this error persist, please contact an administrator.',
                                    type: 'error'
                                }).show()
                            });
                        }, 5000) 
                    })
                })  
            })
            .catch((error)=> {
                if (error.response.status <= 500) {
                    Remotelabz.invitationCode.delete(uuid).then(()=> {
                        setItemToDelete(null);
                        setShowDeleteCodeModal(false);
                        refreshInstance();
                    })
                    .catch((error)=>{
                        new Noty({
                            text: 'An error happened while deleting code. If this error persist, please contact an administrator.',
                            type: 'error'
                        }).show()
                    })
                }
            })
    }

    function openModalWithUuid(uuid) {
        setItemToDelete(uuid);
        setShowDeleteCodeModal(true);
    }

    function refreshInstance() {
        let request

        request = Remotelabz.invitationCode.getByLab(props.lab.id)

        request.then(response => {
            setInvitationCodes(response.data);

            const list = response.data.map((invitation)=> {
                return (
                    <tr key={invitation.id}>
                        <td>{invitation.mail}</td>
                        <td>{invitation.code}</td>
                        <td>{moment(invitation.expiryDate).format("DD/MM/YYYY HH:mm:ss")}</td>
                        <td><button class="btn btn-danger" type="button" onClick={()=>openModalWithUuid(invitation.uuid)}>Delete</button></td>
                    </tr>
                );
            })

            setCodeList(list);
        }).catch(error => {
            if (error.response) {
                if (error.response.status <= 500) {
                    setInvitationCodes(null);
                    setCodeList(null);
                    setLoadingInstanceState(false)
                } else {
                    new Noty({
                        text: 'An error happened while fetching codes. If this error persist, please contact an administrator.',
                        type: 'error'
                    }).show()
                }
            }
        })
    }

    return(
        <div>
            {
                invitationCodes && codeList &&
                <table className="table table-hover">
                    <thead>
                        <tr>
                            <th>Mail</th>
                            <th>Code</th>
                            <th>Expiration Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    {codeList}
                    </tbody>
                </table>  
            }
            <Modal show={showDeleteCodeModal} onHide={() => setShowDeleteCodeModal(false)}>
                <Modal.Header closeButton>
                    <Modal.Title>Delete code</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    The user might still have some instances running. Are you sure you want to delete this code ?
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="default" onClick={() => setShowDeleteCodeModal(false)}>Close</Button>
                    <Button variant="danger" onClick={() => deleteCode(itemToDelete)}>Delete</Button>
                </Modal.Footer>
            </Modal>
        </div>
    )
}