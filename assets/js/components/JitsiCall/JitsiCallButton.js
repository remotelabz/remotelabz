import API from '../../api';
import Remotelabz from '../API';
import React, { Component } from 'react';
import { Button} from 'react-bootstrap';

const api = API.getInstance();

class JitsiCallButton extends Component {
    constructor(props) {
        super(props);

        this.state = {
            lab: this.props.lab,
            user: this.props.user,
            labInstance: this.props.labInstance
        }
    }

    isCallStarted() {
        return this.state.labInstance.jitsiCall.state == "started";
    }

    onMakeACallButtonClick = () => {
        Remotelabz.jitsiCall.start(this.state.lab.uuid, this.state.labInstance.owner.uuid)
            .then(() => {
                this.state.labInstance.jitsiCall.state = "started";
                this.setState({ labInstance: { ...this.state.labInstance}});
            })
    }

    onJoinCallButtonClick = () => {
        if (this.state.labInstance.ownedBy == "group") {
            Remotelabz.jitsiCall.join(this.state.lab.uuid, this.state.labInstance.owner.uuid)
                .then(response => {
                    window.open(response.data);
                })
        }
    }

    render() {
        let callButton = '';

        if (this.props.isOwnedByGroup) {
            if(this.props.isCurrentUserGroupAdmin) {
                if(this.isCallStarted()) {
                    callButton = <Button variant="primary" onClick={this.onJoinCallButtonClick}>Join call</Button>;
                }
                else {
                    callButton = <Button variant="success" onClick={this.onMakeACallButtonClick}>Make a Call</Button>;
                }
            }
            else {
                callButton = <Button variant="primary" onClick={this.onJoinCallButtonClick} disabled={!this.isCallStarted()}>Join call</Button>
            }
        }

        return (callButton);
    }
}

export default JitsiCallButton;