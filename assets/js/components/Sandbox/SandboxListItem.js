import { toast } from 'react-toastify';
import React, { Component } from 'react';
import { ListGroupItem, Button, Spinner, Modal, Form } from 'react-bootstrap';
import API from '../../api';
import Remotelabz from '../API';
import SVG from '../Display/SVG';


class SandboxListItem extends Component {

    api = API.getInstance();

    constructor(props) {
        //console.log("SandboxListItem constructor props:", props);
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
                //console.log("SandboxListItem fetchLabInstance lab:", lab[0]);
            }
            //else console.log("SandboxListItem no fetchLabInstance lab:", lab[0]);
        })
    }

    async onModifyClick(item) {
        //console.log("SandboxListItem onModifyClick item:", item);
        if (this.props.isAnyLoading) {
            toast.warning("Please wait for the current operation to complete.", {});
            return;
        }

        this.setState({ isLoading: true});
        this.props.setIsAnyLoading(true); // Bloquer tous les autres boutons

        let lab;
        let labName;
        let networkInterfaces = [];
        let controlProtocolTypes = [];
        let available = false;

	    await this.api.get("/api/config/workers").then(response => {
			if(typeof response.data == 'object'){
				for(let worker in response.data ){
					if(response.data[worker].available){
						available = true;
						return;
					}
				}
			} 
            //else {
			//	console.log("Error when fetching data for worker availability");
			//}
        })
		.catch(error => {
			console.error("Error when searching for an available worker", error);
		});

	    if(available) {
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
                //console.log("SandboxListItem onModifyClick labObj:", labObj);

                // Add the fields to the lab and a service device if configured on the Remotelabz
                //console.log("labobj", labObj);
            
                try {
                    const response = await Remotelabz.labs.update(labObj);
                    //console.log("SandboxListItem onModifyClick response:", response);
                    //console.log("SandboxListItem onModifyClick response status:", response.status);
                    //console.log("SandboxListItem onModifyClick response data:", response.data);
                
                    if(response.status === 200) {

                        this.setState({ isLoading: true, exist: true, lab: lab});
                        item.flavor = item.flavor.id;
                        item.operatingSystem = item.operatingSystem.id;
                        item.hypervisor = item.hypervisor.id;
                        item.isTemplate = false;
                        item.networkInterfaces.forEach(element => networkInterfaces.push(element.id));
                        item.networkInterfaces = networkInterfaces;
                        item.controlProtocolTypes.forEach(element => console.log(element.id));
                        item.controlProtocolTypes = controlProtocolTypes;
                        
                        // Ajout du device avec gestion d'erreur spécifique
                        try {
                            await this.api.post('/api/labs/' + lab.id + '/devices', item);
                            //console.log("Item", item);
                        } catch (deviceError) {
                            console.error("Error adding device:", deviceError);
                            throw deviceError; // Re-lancer pour être capturé par le catch principal
                        }
                            // Création de l'instance avec gestion d'erreur spécifique
                        try {
                            await Remotelabz.instances.lab.create(lab.uuid, this.props.user.uuid, 'user');
                        } catch (instanceError) {
                            console.error("Error creating lab instance:", instanceError);
                            throw instanceError;
                        }

                        window.location.href = "/admin/sandbox/" + lab.id;
                    } else {
                        this.setState({ isLoading: false, exist: false, lab: lab});
                    }
                } catch (error) {
                    console.error("Catch error : Error creating device sandbox", error);
                    this.setState({ isLoading: false, exist: false, lab: lab});
                    this.props.setIsAnyLoading(false);
                    window.location.href = "/admin/sandbox/";
                }
            }
            // If we want to modify a lab       
            else if (this.props.itemType === "lab") {
                // Do not set `exist` yet: the copy does not exist until the
                // backend call succeeds, and the loading spinner must stay
                // visible in the meantime.
                this.setState({ isLoading: true, lab: this.props.item});
                labName = "Sandbox_Lab_" + this.props.user.uuid + "_" + this.props.item.id;

                // The backend copy endpoint already duplicates the lab content
                // (text objects, devices, banner...). Do not pre-create text
                // objects or pictures here: the target lab does not exist yet,
                // and a single undefined access used to abort the whole action
                // silently (the "Modify" button appeared to do nothing).
                try {
                    const response = await Remotelabz.labs.createcopyLab(this.props.item.id, labName);

                    if (response?.status === 200 && response.data?.id) {
                        const { id: id_lab, uuid } = response.data;
                        lab = { ...this.props.item, id: id_lab, uuid };

                        await Remotelabz.instances.lab.create(uuid, this.props.user.uuid, 'user');

                        window.location.href = "/admin/sandbox/" + id_lab;
                    } else {
                        console.error("Unable to create the sandbox lab copy", response);
                        toast.error("Unable to create the sandbox lab copy. Please try again later.");
                        this.setState({ isLoading: false, exist: false, lab: this.props.item});
                        this.props.setIsAnyLoading(false);
                    }
                }
                catch (error) {
                    console.error("Catch error : Error creating lab copy", error);
                    toast.error("Unable to create the sandbox lab copy. Please try again later.");
                    this.setState({ isLoading: false, exist: false, lab: this.props.item});
                    this.props.setIsAnyLoading(false);
                }
            }    
        } else {
            this.setState({ isLoading: false, exist: false, lab: lab});
            toast.error("No workers available !", {});		
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

	deleteLabSandbox() {
		if(this.state.lab.id){
			if(!this.deleteLab(this.state.lab.id)) {
				this.deleteLab(this.props.item.id);
			} else {
				toast.error("This lab is used by an instance !", {});
			}
		} else {
			this.deleteLab(this.props.item.id);
		}
	}

    render() {
        let divBorder;
        let button;
        //console.log("Rendering SandboxListItem for", this.props.item);
        //console.log("Rendering state is", this.state);
        //console.log("Rendering props ", this.props);

        if(this.state.isLoading) {
            // Keep the "Modify" action visible while the copy is being created
            // (a large imported lab can take a while) instead of switching to
            // the "open sandbox" arrow before the copy actually exists.
            button = (
                <Button className="me-2 mt-2" variant="primary" title="Creating the sandbox lab copy..." data-bs-toggle="tooltip" data-bs-placement="top" disabled>
                    <Spinner animation="border" size="sm" className="me-2" />
                    Modify
                </Button>
                )
        }
        else {
            button = (
                <Button
                    variant="primary"
                    className="me-2 mt-2"
                    onClick={() => this.onModifyClick(this.props.item)}
                    disabled={this.props.isAnyLoading}
                >
                    Modify
                </Button>
            )
        }

        // Loading always wins: the external link is only displayed once the
        // copy exists and no copy is in progress.
        const actionElement = (this.state.isLoading || !this.state.exist || this.state.lab == null)
            ? button
            : (
                <a
                    href={"/admin/sandbox/" + this.state.lab.id}
                    className="btn btn-primary ms-3 me-2 mt-2"
                    title="Open Sandbox"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                >
                    <SVG name="external-link" />
                </a>
            );

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
                    { this.state.isLoading &&
                        <div className="lab-item-infos text-muted">
                            { this.props.itemType === "lab" ? "Creating the lab copy, please wait..." : "Preparing the device, please wait..." }
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
                        <a className="btn btn-secondary me-2 mt-2" role="button" href={"/admin/labs_template/"+this.props.item.id+"/edit"}>Edit</a>
                        <a className="btn btn-danger me-2 mt-2" role="button" onClick={()=>this.setState({showDeleteLabModal: true})}>Delete</a>
                        </>
                    }

                    {//this.props.itemType == "device" && this.props.item.author && this.props.item.author.id == this.props.user.id && (this.props.item.author.roles.includes("ROLE_TEACHER") || this.props.item.author.roles.includes("ROLE_TEACHER_EDITOR")) && (!this.state.exist) &&
                     //   <a className="btn btn-danger me-2 mt-2" role="button" onClick={()=>this.setState({showDeleteDeviceModal: true})}>Delete</a>
                    }
                    
                    {actionElement}
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
                    { this.state.isLoading &&
                        <div className="lab-item-infos text-muted">
                            { this.props.itemType === "lab" ? "Creating the lab copy, please wait..." : "Preparing the device, please wait..." }
                        </div>
                    }
                </div>
                <div className="separator flex-grow-1"></div>

                <div className="lab-item-right d-flex flex-column text-right">
                    <div>
                    {this.props.itemType == "lab" && (this.props.user.roles.includes("ROLE_ADMINISTRATOR") || this.props.user.roles.includes("ROLE_SUPER_ADMINISTRATOR") || ((this.props.item.author.roles.includes("ROLE_TEACHER") || this.props.item.author.roles.includes("ROLE_TEACHER_EDITOR")) && this.props.item.author.id == this.props.user.id)) &&
                        <>
                        <a className="btn btn-secondary me-2 mt-2" role="button" href={"/admin/labs_template/"+this.props.item.id+"/edit"}>Edit</a>
                        <a className="btn btn-danger me-2 mt-2" role="button" onClick={()=>this.setState({showDeleteLabModal: true})}>Delete</a>
                        </>
                    }

                    {//this.props.itemType == "device" && this.props.item.author && this.props.item.author.id == this.props.user.id && (this.props.item.author.roles.includes("ROLE_TEACHER") || this.props.item.author.roles.includes("ROLE_TEACHER_EDITOR")) && (!this.state.exist) &&
                      //  <a className="btn btn-danger me-2 mt-2" role="button" onClick={()=>this.setState({showDeleteDeviceModal: true})}>Delete</a>
                    }
                    {actionElement}
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
                <Button variant="danger" onClick={()=>this.deleteLabSandbox()}>Delete</Button>
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
