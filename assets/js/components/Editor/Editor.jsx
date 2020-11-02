import React from 'react';
import { Modal, Button, OverlayTrigger, Tooltip, Accordion, Card } from 'react-bootstrap';
import Menu from './Menu/Menu';
import ContextualMenu from './Menu/ContextualMenu';
import Canvas from './Display/Canvas';
import DeviceNode from './Elements/DeviceNode';
import { jsPlumb } from 'jsplumb';
import SVG from '../Display/SVG';
import API from '../../api';
import DeviceTemplateSelect from './Form/DeviceTemplateSelect';
import DeviceForm from './Form/DeviceForm';
import EdiText from 'react-editext';
import Skeleton from 'react-loading-skeleton';
import update from 'immutability-helper';
import SimpleMDE from "react-simplemde-editor";
import ReactMarkdown from 'react-markdown';
import Remotelabz, { Device } from '../API';
import Select from 'react-select';
import Noty from 'noty';

export default class Editor extends React.Component {
    jsPlumb = null;
    /** @var {number} labId */
    labId = document.getElementById("labEditor").dataset.id;
    api = API.getInstance({
        beforeSend: () => this.setState({ isLoading: true }),
        responseCallback: () => this.setState({ isLoading: false }),
    });
    zoomMin = 0.5;
    zoomMax = 2;

    constructor(props) {
        super(props);

        this.jsPlumb = jsPlumb.getInstance({
            Endpoint: "Blank",
        });
        this.deviceForm = React.createRef();

        this.state = {
            ready: false,
            fullscreen: false,
            canvasZoom: 1,
            editDescription: false,
            mdeValue: null,
            editDeviceForm: {
                show: false,
                /** @type {import('../API').Device} [devices] */
                device: null,
            },
            addDeviceModal: {
                show: false,
                selectedOption: null,
                referer: "button",
            },
            contextualMenu: {
                show: false,
                x: 0,
                y: 0,
                target: null,
            },
            asideMenu: {
                show: false,
                action: null
            },
            lab: {
                name: null
            },
            /** @type {import('../API').Device[]} devices */
            devices: [],
            isLoading: false,
        }

        document.addEventListener("DOMContentLoaded", () => {
            this.labId = document.getElementById("labEditor").dataset.id;

            Remotelabz.labs.get(this.labId).then(response => this.setState({
                devices: response.data.devices,
                lab: {
                    ...this.state.lab,
                    name: response.data.name,
                    description: response.data.description
                },
                ready: true
            }));
        });
    }

    getLabDeviceRequest = id => {
        return this.api.get('/api/devices/' + id);
    }

    updateLabRequest = (id, params) => {
        return this.api.put('/api/labs/' + id, params);
    }

    /** @param {Device} device */
    addDeviceRequest = device => {
        device.networkInterfaces = null
        this.api.post('/api/labs/' + this.labId + '/devices', device)
            .then(response => this.addDevice(response.data))
            ;
    }

    updateDeviceRequest = device => {
        return this.api.put('/api/devices/' + device.id, device);
    }

    updateDevicePositionRequest = device => {
        this.api.put('/api/devices/' + device.props.id + '/editor-data', {
            x: device.state.left,
            y: device.state.top
        });
    }

    deleteDeviceRequest = () => {
        this.api.delete('/api/devices/' + this.state.contextualMenu.target.id)
            .then(() => {
                const devices = this.state.devices.filter((device) => {
                    return device.id != this.state.contextualMenu.target.id;
                });
                this.setState({ devices });
            })
            ;
    }

    addDevice = (device) => {
        this.setState({
            devices: [
                ...this.state.devices,
                device
            ]
        });
    }

    handleCreateDevice = () => this.setState({ addDeviceModal: { ...this.state.addDeviceModal, show: true } });
    handleEditDevice = () => {
        // this.hideAsideMenu();
        let device = this.state.devices.find(device => {
            return device.id == this.state.contextualMenu.target.id;
        });
        this.setState({
            editDeviceForm: {
                ...this.state.editDeviceForm,
                device: device.id,
                show: true,
            },
            asideMenu: {
                show: true,
                action: 'edit'
            }
        });
    }

    handleEditDescription = () => this.setState({ editDescription: true });
    handleCancelEditDescription = () => this.setState({ editDescription: false });
    handleSaveEditDescription = () => {
        this.updateLabRequest(this.labId, {
            description: this.state.mdeValue,
        })
            .then(response => {
                this.setState({ lab: { ...this.state.lab, description: this.state.mdeValue }, editDescription: false });
            })
    }
    handleChangeDescription = mdeValue => {
        this.setState({ mdeValue });
    }

    onSubmitEditDevice = device => {
        this.updateDeviceRequest(device)
            .then(response => {
                this.hideAsideMenu();
                const updatedDeviceIndex = this.state.devices.findIndex(value => { return device.id == value.id });
                this.setState({
                    devices: update(this.state.devices, { [updatedDeviceIndex]: { $set: response.data } })
                });
            });
    }

    handleDeleteDevice = () => {
        this.hideAsideMenu();
        this.deleteDeviceRequest();
    }

    onHideAddDeviceModal = () => this.setState({ addDeviceModal: { ...this.state.addDeviceModal, show: false } });

    onValidateAddDeviceModal = () => {
        let device = this.state.addDeviceModal.selectedOption;
        device.flavor = device.flavor.id;
        device.operatingSystem = device.operatingSystem.id;
        device.isTemplate = false;
        console.log(device);

        this.addDeviceRequest(device);
        this.onHideAddDeviceModal();
    }

    onHideDeviceModal = () => this.setState({ deviceModal: { ...this.state.deviceModal, show: false } });

    onValidateDeviceModal = () => {
        let device = this.state.deviceModal.selectedOption;
        device.flavor = device.flavor.id;
        device.operatingSystem = device.operatingSystem.id;
        device.isTemplate = false;

        this.addDeviceRequest(device);
        this.onHideDeviceModal();
    }

    toggleFullscreen = () => this.setState({ fullscreen: !this.state.fullscreen });

    /**
     * @param {React.MouseEvent} e
     */
    onContextMenu = (e) => {
        e.preventDefault();
        const position = e.target.getBoundingClientRect();
        const y = position.top + (e.clientY - position.top);
        const x = position.left + (e.clientX - position.left);
        const target = e.target.closest('.node');
        this.setState({
            contextualMenu: { x, y, target, show: true },
        })
        document.onmousedown = this.onClickWhileOpenContextMenu;
    }

    /**
     * @param {React.MouseEvent} e
     */
    onClickWhileOpenContextMenu = (e) => {
        if (!e.target.closest('.editor-contextual-menu')) {
            this.closeContextMenu();
        } else {
            let event = document.createEvent("MouseEvent");
            event.initMouseEvent("click", true, false);
            event.eventName = "click";
            e.target.closest('.editor-contextual-menu-option').dispatchEvent(event);
            this.closeContextMenu();
        }
    }

    closeContextMenu = () => {
        if (this.state.contextualMenu) {
            this.setState({
                contextualMenu: { ...this.state.contextualMenu, show: false },
            });
        }

        if (document.onmousedown) {
            document.onmousedown = null;
        }
    }

    onDeviceDrag = () => this.jsPlumb.repaintEverything();

    onDeviceMoved = device => this.updateDevicePositionRequest(device);

    componentDidUpdate = () => this.jsPlumb.repaintEverything();

    onChangeDeviceSelect = selectedOption => {
        this.setState({ addDeviceModal: { ...this.state.addDeviceModal, selectedOption } })
    }

    onZoomIn = () => {
        let zoom = this.state.canvasZoom;
        if (parseFloat(zoom) < parseFloat(this.zoomMax)) {
            zoom += 0.1;
        }

        this.setState({
            canvasZoom: zoom,
        });
    }

    onZoomOut = () => {
        let zoom = this.state.canvasZoom;
        if (parseFloat(zoom) > parseFloat(this.zoomMin)) {
            zoom -= 0.1;
        }

        this.setState({
            canvasZoom: zoom,
        });
    }

    onNameSave = val => {
        if (val != this.state.lab.name) {
            this.updateLabRequest(this.labId, { name: val })
            .then(() => this.setState({ lab: { ...this.state.lab, name: val } }))
        }
    }

    getEditorClassNames = () => { return "editor" + (this.state.fullscreen ? " fullscreen" : ""); };

    getContextualMenuItems = () => {
        // If a node is targeted
        if (this.state.contextualMenu.target) {
            return (<>
                <ContextualMenu.Item onClick={this.handleEditDevice}>
                    <SVG name="pencil" className="image-sm v-sub"></SVG> Edit
                </ContextualMenu.Item>
                <ContextualMenu.Item onClick={this.handleDeleteDevice}>
                    <SVG name="remove" className="image-sm v-sub"></SVG> Delete
                </ContextualMenu.Item>
            </>);
        } else {
            return (<>
                <ContextualMenu.Item onClick={this.handleCreateDevice}>
                    <SVG name="plus-square" className="image-sm v-sub"></SVG> Add device
                </ContextualMenu.Item>
            </>);
        }
    }

    getAsideMenuChildren = () => {
        const id = this.state.editDeviceForm.device;
        const device = this.state.devices.find(d => d.id == id);

        if (this.state.asideMenu.action === 'edit') {
            return (<>
                <h2>Edit device</h2>
                <DeviceForm onSubmit={this.onSubmitEditDevice} device={device} />
                <hr />
                <h2 className="mb-3">Network interfaces</h2>
                {device.networkInterfaces.map((networkInterface, index) => {
                    const accessTypeOptions = [
                        { value: '', label: 'None', id: networkInterface.id },
                        { value: 'VNC', label: 'VNC', id: networkInterface.id }
                    ];

                    return <div key={networkInterface.uuid} className="device-network-interface-item px-3 py-3 mb-3">
                        <h4 className="mb-2">NIC #{index + 1}</h4>
                        <div className="form-group">
                            <label className="form-label">Access type</label>
                            <Select
                                options={accessTypeOptions}
                                menuPlacement={'top'}
                                className='react-select-container'
                                classNamePrefix="react-select"
                                defaultValue={accessTypeOptions.find(v => (!networkInterface.accessType && v.value == '') || (networkInterface.accessType && v.value == networkInterface.accessType))}
                                onChange={this.onNetworkInterfaceProtocolChange}
                            />
                        </div>
                        <Button variant="danger" onClick={() => this.onNetworkInterfaceRemove(networkInterface.id)} block>
                            <SVG name="remove" className="image-sm v-sub" /> Remove
                        </Button>
                    </div>
                })}
                <Button variant="success" onClick={() => this.onNetworkInterfaceCreate(device.id)} block>
                    <SVG name="plus-square" className="image-sm v-sub" /> Add network interface
                </Button>
            </>);
        }

        return null;
    }

    /**
     * @param {number} id ID of the device
     */
    onNetworkInterfaceCreate = (id) => {
        Remotelabz.networkInterfaces.create({ device: id })
        .then(response => {
            const devices = this.state.devices;
            devices.find(d => d.id == id).networkInterfaces.push(response.data);
            this.setState({devices});
        });
    }

    /** @param {{value: string, label: string, id: number}} option */
    onNetworkInterfaceProtocolChange = (option) => {
        Remotelabz.networkInterfaces.update(option.id, {
            accessType: option.value != '' ? option.value : null
        })
        .then(response => {
            let devices = this.state.devices;
            devices.find(d => d.id == this.state.editDeviceForm.device).networkInterfaces.find(nic => nic.id == option.id).accessType = response.data.accessType;
            this.setState({devices}, () => new Noty({type: 'success', text: 'NIC access type has been changed.'}).show())
        })
    }

    /** @param {number} id ID of the NIC */
    onNetworkInterfaceRemove = (id) => {
        Remotelabz.networkInterfaces.delete(id)
        .then(() => {
            let devices = this.state.devices;
            devices.find(d => d.id == this.state.editDeviceForm.device).networkInterfaces = devices.find(d => d.id == this.state.editDeviceForm.device).networkInterfaces.filter(nic => nic.id != id);
            this.setState({devices}, () => new Noty ({type: 'success', text: 'NIC has been removed from device.'}).show());
        })
    }

    hideAsideMenu = () => this.setState({ asideMenu: { show: false, action: null } });

    render() {
        return (<>
            <div className="editor-lab-name mb-3 mt-3">
                <div>
                    {this.state.lab.name ?
                        <EdiText
                            type='text'
                            value={this.state.lab.name}
                            onSave={this.onNameSave}
                            showButtonsOnHover={true}
                            editOnViewClick={true}
                            mainContainerClassName="editor-title"
                            editButtonContent="✎"
                            editButtonClassName="editor-title-edit-button"
                            submitOnEnter
                        />
                        :
                        <Skeleton height={24} />}</div>
            </div>
            {this.state.ready ?
                <div className="mb-3">
                    {this.state.editDescription ?
                        <div>
                            <SimpleMDE value={this.state.lab.description} onChange={this.handleChangeDescription} />
                            <div className="d-flex">
                                <Button variant="success" onClick={this.handleSaveEditDescription}>Save</Button>
                                <div className="flex-grow-1" />
                                <Button variant="default" onClick={this.handleCancelEditDescription}>Cancel</Button>
                            </div>
                        </div>
                        :
                        <Accordion className="lab-description">
                            <Card>
                                <Accordion.Toggle as={Card.Header} variant="link" eventKey="0">
                                    <div>
                                        <SVG name="text-description" className="v-sub s18 mr-2" /> Description
                                </div>
                                    <div>
                                        <Button variant="default" onClick={(e) => { e.stopPropagation(); this.handleEditDescription(); }}><SVG name="pencil" /></Button>
                                    </div>
                                </Accordion.Toggle>
                                <Accordion.Collapse eventKey="0">
                                    <Card.Body>
                                        <div className="text-muted">
                                            <ReactMarkdown source={this.state.lab.description || "No description"} />
                                        </div>
                                    </Card.Body>
                                </Accordion.Collapse>
                            </Card>
                        </Accordion>

                    }
                </div>
                :
                <Skeleton count={6} />
            }
            {this.state.ready ?
                <div className="editor-wrapper mb-3">
                    <div id="editor" className={this.getEditorClassNames()}>
                        {/* ADD DEVICE MODAL */}
                        <Modal size="lg" show={this.state.addDeviceModal.show} onHide={this.onHideAddDeviceModal}>
                            <Modal.Header closeButton>
                                <Modal.Title>Add a new device</Modal.Title>
                            </Modal.Header>
                            <Modal.Body>
                                <DeviceTemplateSelect onChange={this.onChangeDeviceSelect} />
                            </Modal.Body>
                            <Modal.Footer>
                                <Button variant="success" onClick={this.onValidateAddDeviceModal} disabled={!this.state.addDeviceModal.selectedOption ? true : false}>Add</Button>
                            </Modal.Footer>
                        </Modal>

                        {/* CONTEXTUAL MENU */}
                        <ContextualMenu
                            id="editorContextualMenu"
                            x={this.state.contextualMenu.x}
                            y={this.state.contextualMenu.y}
                            show={this.state.contextualMenu.show}
                        >
                            {this.getContextualMenuItems()}
                        </ContextualMenu>

                        {/* TOP MENU */}
                        <Menu
                            onCreateDeviceRequest={this.handleCreateDevice}
                            onToggleFullscreen={this.toggleFullscreen}
                            onZoomIn={this.onZoomIn}
                            onZoomOut={this.onZoomOut}
                            className="editor-menu"
                        />

                        {/* CANVAS */}
                        <div className="editor-canvas-wrapper overflow-hidden">
                            <Canvas onContextMenu={this.onContextMenu} onZoomIn={this.onZoomIn} onZoomOut={this.onZoomOut} zoom={this.state.canvasZoom}>
                                {this.state.devices.map((device) => (
                                    <DeviceNode
                                        key={device.id}
                                        name={device.name}
                                        id={device.id}
                                        x={device.editorData.x}
                                        y={device.editorData.y}
                                        scale={this.state.canvasZoom}
                                        onMoved={this.onDeviceMoved}
                                        onDrag={this.onDeviceDrag}
                                        onContextMenu={this.onContextMenu}
                                    />
                                ))}
                            </Canvas>

                            {/* SIDE MENU */}
                            {this.state.asideMenu.show && (
                                <div style={{ overflowY: 'scroll' }}>
                                    <aside className="editor-aside-toolbar">
                                        <OverlayTrigger placement="top" overlay={<Tooltip>Close side menu</Tooltip>}>
                                            <Button variant="danger" onClick={this.hideAsideMenu} className="float-right"><SVG name="close" className="image-sm v-sub"></SVG></Button>
                                        </OverlayTrigger>
                                        {this.getAsideMenuChildren()}
                                    </aside>
                                </div>
                            )}
                        </div>

                        {/* TOOLBAR */}
                        <div className="editor-toolbar-wrapper">
                            <div className="text-muted">Zoom : {Math.round(this.state.canvasZoom * 100)}%</div>
                            <div className="flex-separator"></div>
                            <div className="toolbar-loading-wrapper text-muted">
                                {
                                    this.state.isLoading ?
                                        <><SVG name="spinner" className="image-sm v-sub"></SVG> Loading...</>
                                        :
                                        <>Saved!</>
                                }
                            </div>
                        </div>
                    </div>
                </div> : <Skeleton height={800} />}
        </>)
    }
}