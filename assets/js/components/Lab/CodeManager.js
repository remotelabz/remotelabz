import React, { useState, useEffect } from 'react';
import Remotelabz from '../API';
import moment from 'moment/moment';
import { Button, Modal} from 'react-bootstrap';

export default function CodeManager(props = {lab}) {
    const [invitationCodes, setInvitationCodes] = useState();
    const [loadingInstanceState, setLoadingInstanceState] = useState();
    const [codeList, setCodeList] = useState();

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
            refreshInstance()
        })
        .catch((error)=>{
            new Noty({
                text: 'An error happened while deleting code. If this error persist, please contact an administrator.',
                type: 'error'
            }).show()
        })
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
                        <td><button class="btn btn-danger" type="button" onClick={()=>deleteCode(invitation.uuid)}>Delete</button></td>
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
        </div>
    )
}