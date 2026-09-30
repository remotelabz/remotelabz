import { Badge } from 'react-bootstrap';
import React, { Component } from 'react';

class InstanceStateBadge extends Component {
    constructor(props) {
        super(props);

        this.state = {
            state: props.state
        };
    }

    render() {
        let badge;

        switch (this.props.state) {
            case 'stopped':
                badge = <Badge bg="default" {...this.props}>Stopped</Badge>
                break;

            case 'starting':
                badge = <Badge bg="warning" text="white" {...this.props}>Starting</Badge>
                break;

            case 'stopping':
                badge = <Badge bg="warning" text="white" {...this.props}>Stopping</Badge>
                break;

            case 'resetting':
                badge = <Badge bg="warning" text="white" {...this.props}>Resetting</Badge>
                break;

            case 'reset':
                badge = <Badge bg="info" text="white" {...this.props}>Reset</Badge>
                break;

            case 'started':
                badge = <Badge bg="success" text="white" {...this.props}>Started</Badge>
                break;

            case 'exporting':
                badge = <Badge bg="warning" text="white" {...this.props}>Exporting</Badge>
                break;

            case 'exported':
                    badge = <Badge bg="success" text="white" {...this.props}>Exported</Badge>
                    break;

            case 'error':
                badge = <Badge bg="danger" text="white" {...this.props}>Error</Badge>
                break;

            default:
                badge = <Badge bg="default" {...this.props}>{this.state.state}</Badge>
        }

        return badge;
    }
}

export default InstanceStateBadge;