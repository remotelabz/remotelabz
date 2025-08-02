import React, { Component } from 'react';
import { Button, Spinner, Modal } from 'react-bootstrap';
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
            exist: false,
            showDeleteLabModal: false,
            showDeleteDeviceModal: false
        }
        this.fetchLabInstance();
    }
    

    fetchLabInstance = () => {
        if(this.props.itemType == "device") {
            var labName = "Sandbox_Device_" + this.props.user.uuid + "_" + this.props.item.id;
        }
        else if (this.props.itemType == "lab") {
            var labName = "Sandbox_Lab_" + this.props.user.uuid + "_" + this.props.item.id;
        }
        
        let lab;

        this.api.get("/api/labs?search=" + labName + "&author=" + this.props.user.id).then(response => {
            lab = response.data;

            if(lab) {
                this.setState({exist: true, lab: lab[0]});
            }
        })
    }

    async onModifyClick(item) {
        //console.log("onModifyClick", item);
        this.setState({ isLoading: true});
        let lab;
        let labName;
        let networkInterfaces = [];
        let controlProtocolTypes = [];
        
        // If we want to modify a device
        if(this.props.itemType === "device") {
            // Create a new lab to work on a copy of the original lab
            // /api/labs returns the id of the created lab
            await this.api.post("/api/labs").then(response => {
                lab = response.data
            });
            labName = "Sandbox_Device_" + this.props.user.uuid + "_" + this.props.item.id;
            var fields = {name: labName};
            var labObj = {id: lab.id, fields: fields};

            // Add the fields to the lab and a service device if configured on the Remotelabz
            //console.log("labobj", labObj);
            await Remotelabz.labs.update(labObj);
            item.flavor = item.flavor.id;
            item.operatingSystem = item.operatingSystem.id;
            item.hypervisor = item.hypervisor.id;
            item.isTemplate = false;
            item.networkInterfaces.forEach(element => networkInterfaces.push(element.id));
            //item.networkInterfaces.forEach(element => console.log(element.id));
            item.networkInterfaces = networkInterfaces;
            item.controlProtocolTypes.forEach(element => controlProtocolTypes.push(element.id));
            //item.controlProtocolTypes.forEach(element => console.log(element.id));
            item.controlProtocolTypes = controlProtocolTypes;
            //Add Service device if Service OS was configured on the FemoteLabz
            await this.api.post('/api/labs/' + lab.id + '/devices', item);
        }      
        // If we want to modify a lab       
        else if (this.props.itemType === "lab") {
            labName = "Sandbox_Lab_" + this.props.user.uuid + "_" + this.props.item.id;
            var fields = {name: labName, description: this.props.item.description, shortDescription: this.props.item.shortDescription}
            if (this.props.item.hasTimer) {
                let timerArray = this.props.item.timer.split(":");
                fields = {...fields, hasTimer: this.props.item.hasTimer, timer: {hour: Math.round(timerArray[0]), minute: Math.round(timerArray[1])}
                };
            }

            for(var textobject of item.textobjects){
                var textObj = {labid: lab.id, fields:{name: textobject.name, type: textobject.type, data: textobject.data}};
                if (typeof textobject.newdata !== 'undefined') {
                    textObj = {...textObj, newdata: textobject.newdata};
                }
                await Remotelabz.textObjects.new(textObj);
            }
            for(var picture of item.pictures){
                var pictureObj = {labid: lab.id, fields:{name: picture.name, type: picture.type, labid: item.id,  height: picture.height, width: picture.width, map: picture.map}};
                await Remotelabz.pictures.new(pictureObj);
               
            }

            try {
                const response = await Remotelabz.labs.createcopyLab(this.props.item.id, labName)
                //console.log("response:", response);

                if(response.status === 200) {
                    this.setState({ isLoading: true, exist: true, lab: lab});
                    const { id: id_lab, uuid } = response.data;
                    lab = { ...lab, id: id_lab, uuid }; // Met à jour lab avec les nouvelles valeurs
                    
                    //console.log("Lab id:", lab.id);
                    Remotelabz.labs.copyBanner(this.props.item.id, lab.id);

                    await Remotelabz.instances.lab.create(lab.uuid, this.props.user.uuid, 'user');
                    window.location.href = "/admin/sandbox/" + lab.id;
                } else {
                    this.setState({ isLoading: false, exist: false, lab: lab});
                }
            }
            catch (error) {
                    console.error("Catch error : Error creating lab copy", error);
                    this.setState({ isLoading: false, exist: false, lab: lab});
                    window.location.href = "/admin/sandbox/";
                   /* Noty.error({
                        text: "Error creating lab copy: " + error.message,
                        timeout: 5000,
                        progressBar: true,
                        closeWith: ['click', 'button'],
                    });*/
                }
        }
        
    }

    async deleteLab(id) {
        this.setState({ isLoading: true, exist: true});
        await Remotelabz.labs.delete(id);
        this.setState({ isLoading: false, exist: false});

        window.location.href = "/admin/sandbox"
    }

    async deleteDevice(id) {
        await Remotelabz.devices.delete(id);
        window.location.href = "/admin/sandbox"
    }

    render() {
        let divBorder;
        let button;
	    //console.log("Rendering SandboxListItem for", this.props.item.name);

        if(this.state.isLoading) {
            button = (<Button className="ml-3" variant="dark" title="Starting your instance" data-toggle="tooltip" data-placement="top" disabled>
                <Spinner animation="border" size="sm" />
            </Button>)
        }
        else {
            button = (<Button variant="primary" className="mr-2 mt-2" onClick={() => this.onModifyClick(this.props.item)}> Modify </Button>
            )
        }

        if(this.props.itemsLength != (this.props.index +1)) {
            divBorder = (
            
            <div className="wrapper d-flex align-items-center lab-item border-bottom">
                <div className="lab-item-left d-flex flex-column">
                    <div>
                        {this.props.item.name}
                    </div>
                    { this.props.itemType == "device" &&
                        <div className="lab-item-infos text-muted">
                            (Type: {this.props.item.type}, OS: {this.props.item.operatingSystem.name})
                        </div>
                    }
                </div>
                <div className="separator flex-grow-1"></div>

                <div className="lab-item-right d-flex flex-column text-right">
                    <div>
                    {
                        this.props.itemType == "lab"
                        && (this.props.user.roles.includes("ROLE_ADMINISTRATOR") 
                                || this.props.user.roles.includes("ROLE_SUPER_ADMINISTRATOR")
                                || (
                                    (this.props.item.author.roles.includes("ROLE_TEACHER") || this.props.item.author.roles.includes("ROLE_TEACHER_EDITOR"))
                                    && this.props.item.author.id == this.props.user.id
                                    )
                            ) 
                        && <>
                        <a className="btn btn-secondary mr-2 mt-2" role="button" href={"/admin/labs_template/"+this.props.item.id+"/edit"}>Edit</a>
                        <a className="btn btn-danger mr-2 mt-2" role="button" onClick={()=>this.setState({showDeleteLabModal: true})}>Delete</a>
                        </>
                    }

                    {//this.props.itemType == "device" && this.props.item.author && this.props.item.author.id == this.props.user.id && (this.props.item.author.roles.includes("ROLE_TEACHER") || this.props.item.author.roles.includes("ROLE_TEACHER_EDITOR")) && (!this.state.exist) &&
                     //   <a className="btn btn-danger mr-2 mt-2" role="button" onClick={()=>this.setState({showDeleteDeviceModal: true})}>Delete</a>
                    }
                    
                    { this.state.exist ?
                        <a 
                            href={"/admin/sandbox/" + this.state.lab.id}
                            className="btn btn-primary ml-3 mr-2 mt-2"
                            title="Open Sandbox"
                            data-toggle="tooltip"
                            data-placement="top"
                        >
                            <SVG name="external-link" />
                        </a>
                    :
                        button
                    }
                    </div>
                </div>
            </div>
            )
        }
        else {
            divBorder = (
                <div className="wrapper d-flex align-items-center lab-item">
                <div className="lab-item-left d-flex flex-column">
                    <div>
                        {this.props.item.name}
                    </div>
                    {this.props.itemType == "device" &&
                        <div className="lab-item-infos text-muted">
                            (Type: {this.props.item.type}, OS: {this.props.item.operatingSystem.name})
                        </div>
                    }
                </div>
                <div className="separator flex-grow-1"></div>

                <div className="lab-item-right d-flex flex-column text-right">
                    <div>
                    {this.props.itemType == "lab" && (this.props.user.roles.includes("ROLE_ADMINISTRATOR") || this.props.user.roles.includes("ROLE_SUPER_ADMINISTRATOR") || ((this.props.item.author.roles.includes("ROLE_TEACHER") || this.props.item.author.roles.includes("ROLE_TEACHER_EDITOR")) && this.props.item.author.id == this.props.user.id)) &&
                        <>
                        <a className="btn btn-secondary mr-2 mt-2" role="button" href={"/admin/labs_template/"+this.props.item.id+"/edit"}>Edit</a>
                        <a className="btn btn-danger mr-2 mt-2" role="button" onClick={()=>this.setState({showDeleteLabModal: true})}>Delete</a>
                        </>
                    }

                    {//this.props.itemType == "device" && this.props.item.author && this.props.item.author.id == this.props.user.id && (this.props.item.author.roles.includes("ROLE_TEACHER") || this.props.item.author.roles.includes("ROLE_TEACHER_EDITOR")) && (!this.state.exist) &&
                      //  <a className="btn btn-danger mr-2 mt-2" role="button" onClick={()=>this.setState({showDeleteDeviceModal: true})}>Delete</a>
                    }
                    { this.state.exist ?
                        <a 
                            href={"/admin/sandbox/" + this.state.lab.id}
                            className="btn btn-primary ml-3 mr-2 mt-2"
                            title="Open Sandbox"
                            data-toggle="tooltip"
                            data-placement="top"
                        >
                            <SVG name="external-link" />
                        </a>
                    :
                        button
                    }
                    </div>
                </div>
            </div>
            )
        }

        return (
            <>
            {divBorder}
            <Modal show={this.state.showDeleteLabModal} onHide={()=>this.setState({showDeleteLabModal: false})}>
            <Modal.Header closeButton>
                <Modal.Title>Leave lab</Modal.Title>
            </Modal.Header>
            <Modal.Body>
            Do you confirm you want to delete this lab ?
            </Modal.Body>
            <Modal.Footer>
                <Button variant="default" onClick={()=>this.setState({showDeleteLabModal: false})}>Close</Button>
                <Button variant="danger" onClick={()=>this.deleteLab(this.props.item.id)}>Delete</Button>
            </Modal.Footer>
        </Modal>
        <Modal show={this.state.showDeleteDeviceModal} onHide={()=>this.setState({showDeleteDeviceModal: false})}>
            <Modal.Header closeButton>
                <Modal.Title>Delete device</Modal.Title>
            </Modal.Header>
            <Modal.Body>
            Do you confirm you want to delete this device ?
            </Modal.Body>
            <Modal.Footer>
                <Button variant="default" onClick={()=>this.setState({showDeleteDeviceModal: false})}>Close</Button>
                <Button variant="danger" onClick={()=>this.deleteDevice(this.props.item.id)}>Delete</Button>
            </Modal.Footer>
        </Modal>
        </>
        );
    }
}
//console.log("test")
export default SandboxListItem;
