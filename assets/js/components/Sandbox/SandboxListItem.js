import React, { Component } from 'react';
import { Button, Spinner } from 'react-bootstrap';
import API from '../../api';
import Remotelabz from '../API';

class SandboxListItem extends Component {

    api = API.getInstance();

    constructor(props) {
        super(props);

        this.state = {
            isLoading: false,
        }
    }

    async onModifyClick(device) {
        this.setState({ isLoading: true});
        let lab;
        let networkInterfaces = [];
        // Create Lab
        await this.api.post("/api/labs").then(response => {
            lab = response.data
        });
        // Add device to lab
        device.flavor = device.flavor.id;
        device.operatingSystem = device.operatingSystem.id;
        device.isTemplate = false;
        device.networkInterfaces.forEach(element => networkInterfaces.push(element.id));
        device.networkInterfaces = networkInterfaces;

        await this.api.post('/api/labs/' + lab.id + '/devices', device);

        // Create and start a lab instance
        await Remotelabz.instances.lab.create(lab.uuid, this.props.user.uuid, 'user');
        // Redirect to "sandbox"        
        window.location.href = "/admin/devices_sandbox/" + lab.id;
    }

    render() {
        let divBorder;
        let button;

        if(this.state.isLoading) {
            button = (<Button className="ml-3" variant="dark" title="Starting your instance" data-toggle="tooltip" data-placement="top" disabled>
                <Spinner animation="border" size="sm" />
            </Button>)
        }
        else {
            button = (<Button variant="primary" onClick={() => this.onModifyClick(this.props.device)}> Modify </Button>
            )
        }

        if(this.props.devicesLength != (this.props.index +1)) {
            divBorder = (
            <div className="wrapper d-flex justify-content-between align-items-center py-2 border-bottom">
                {this.props.device.name}
                {button}
            </div>)
        }
        else {
            divBorder = (
                <div className="wrapper d-flex justify-content-between align-items-center py-2">
                    {this.props.device.name}
                    {button}
                </div>)
        }

        return (
            divBorder
        );
    }
}

export default SandboxListItem;