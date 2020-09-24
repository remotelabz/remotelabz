import Remotelabz from '../API';
import SVG from '../Display/SVG';
import React, { useState } from 'react';
import { Button } from 'react-bootstrap';

function JitsiCallButton(props) {
    const [loading, setLoading] = useState(false);

    let isCallStarted = () => {
        return props.labInstance.jitsiCall.state == "started";
    }

    let onMakeACallButtonClick = () => {
        setLoading(true);
        Remotelabz.jitsiCall.start(props.lab.uuid, props.labInstance.owner.uuid).finally(() => {
            setLoading(false);
            if(props.onStartedCall)
                props.onStartedCall()
        });
    }

    let onJoinCallButtonClick = () => {
        if (props.labInstance.ownedBy == "group") {
            Remotelabz.jitsiCall.join(props.lab.uuid, props.labInstance.owner.uuid)
                .then(response => {
                    window.open(response.data);
                })
        }
    }

    if(props.isCurrentUserGroupAdmin) {
        if(isCallStarted()) {
            return <Button variant="primary" onClick={onJoinCallButtonClick}><SVG name="volume-up" /> Join call</Button>;
        }
        else {
            return <Button variant="success" onClick={onMakeACallButtonClick}>Make a Call</Button>;
        }
    }
    else {
        return <Button variant="primary" onClick={onJoinCallButtonClick} disabled={loading}>Join call</Button>
    }
}

export default JitsiCallButton;