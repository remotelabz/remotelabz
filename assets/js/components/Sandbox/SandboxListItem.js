import React, { Component } from 'react';
import { Button, Spinner } from 'react-bootstrap';
import API from '../../api';
import Remotelabz from '../API';
import SVG from '../Display/SVG';

class SandboxListItem extends Component {

    api = API.getInstance();

    constructor(props) {
        super(props);

        this.state = {
            lab: {},
            isLoading: false,
            exist: false
        }

        this.fetchLabInstance();
    }

    fetchLabInstance = () => {
        var labName = "Sandbox_" + this.props.user.uuid + "_" + this.props.device.id;
        let lab;

        this.api.get("/api/labs?search=" + labName + "&author=" + this.props.user.id).then(response => {
            lab = response.data;

            if(lab) {
                this.setState({exist: true, lab: lab[0]});
            }
        })
    }

    async onModifyClick(device) {
        
        this.setState({ isLoading: true});
        let lab;
        let networkInterfaces = [];
        // Create Lab
        await this.api.post("/api/labs").then(response => {
            lab = response.data
        });

        var labName = "Sandbox_" + this.props.user.uuid + "_" + device.id;
        var labObj = { id: lab.id, fields: {name: labName}};
        Remotelabz.labs.update(labObj);
        // Add device to lab
        device.flavor = device.flavor.id;
        device.operatingSystem = device.operatingSystem.id;
        device.isTemplate = false;
        device.networkInterfaces.forEach(element => networkInterfaces.push(element.id));
        device.networkInterfaces.forEach(element => console.log(element.id));
        device.networkInterfaces = networkInterfaces;

        await this.api.post('/api/labs/' + lab.id + '/devices', device);

        // Create and start a lab instance
        await Remotelabz.instances.lab.create(lab.uuid, this.props.user.uuid, 'user');
  
        this.setState({ isLoading: false, exist: true, lab: lab});
        // Redirect to Sandbox
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
                { this.state.exist ?
                    <a 
                        href={"/admin/devices_sandbox/" + this.state.lab.id}
                        className="btn btn-primary ml-3"
                        title="Open Device Sandbox"
                        data-toggle="tooltip"
                        data-placement="top"
                    >
                        <SVG name="external-link" />
                    </a>
                :
                    button
                }
            </div>)
        }
        else {
            divBorder = (
                <div className="wrapper d-flex justify-content-between align-items-center py-2">
                    {this.props.device.name}
                    { this.state.exist ?
                        <a 
                            href={"/admin/devices_sandbox/" + this.state.lab.id}
                            className="btn btn-primary ml-3"
                            title="Open Device Sandbox"
                            data-toggle="tooltip"
                            data-placement="top"
                        >
                            <SVG name="external-link" />
                        </a>
                    :
                        button
                    }
                </div>)
        }

        return (
            divBorder
        );
    }
}

export default SandboxListItem;