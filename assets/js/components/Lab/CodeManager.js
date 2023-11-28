import React, { useState, useEffect } from 'react';
import Remotelabz from '../API';
import moment from 'moment/moment';
import { Button, Modal} from 'react-bootstrap';

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
                    <Modal.Title>Leave labs</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    If you leave these labs, <strong>all your instances will be deleted and all virtual machines associated will be destroyed.</strong> Are you sure you want to leave these labs ?
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="default" onClick={() => setShowDeleteCodeModal(false)}>Close</Button>
                    <Button variant="danger" onClick={() => deleteCode(itemToDelete)}>Leave</Button>
                </Modal.Footer>
            </Modal>
        </div>
    )
}