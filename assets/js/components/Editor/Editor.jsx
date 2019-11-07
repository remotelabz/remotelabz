import React from 'react';
import { Modal, Button } from 'react-bootstrap';
import Menu from './Menu/Menu';
import ContextualMenu from './Menu/ContextualMenu';
import Canvas from './Display/Canvas';
import Device from './Elements/Device';
import { jsPlumb } from 'jsplumb';
import SVG from '../Display/SVG';
import API from '../../api';
import DeviceTemplateSelect from './Form/DeviceTemplateSelect';
import DeviceForm from './Form/DeviceForm';
import EdiText from 'react-editext'

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
            fullscreen: false,
            canvasZoom: 1,
            deviceModal: {
                show: false,
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
            lab: {
                name: ''
            },
            /** @type {array} */
            devices: [],
            isLoading: false,
        }

        document.addEventListener("DOMContentLoaded", () => {
            this.labId = document.getElementById("labEditor").dataset.id;

            this.getLabDevicesRequest();
        });
    }

    getLabDevicesRequest = () => {
        this.api.get('/api/labs/' + this.labId)
            .then(response => this.setState({
                devices: response.data.devices,
                lab: {
                    ...this.state.lab,
                    name: response.data.name
                }
            }))
        ;
    }

    getLabDeviceRequest = id => {
        return this.api.get('/api/devices/' + id);
    }

    updateLabRequest = (id, params) => {
        return this.api.put('/api/labs/' + id, params);
    }

    addDeviceRequest = device => {
        this.api.post('/api/labs/' + this.labId + '/devices', device)
            .then(response => this.addDevice(response.data))
        ;
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
        this.setState({devices: [
            ...this.state.devices,
            device
        ]});
    }

    handleCreateDevice = () => this.setState({ addDeviceModal: { ...this.state.addDeviceModal, show: true }});
    handleEditDevice = () => {
        let device = this.state.devices.find(device => {
            return device.id == this.state.contextualMenu.target.id;
        });
        console.log(device);
        this.setState({
            deviceModal: {
                ...this.state.deviceModal,
                device,
                show: true,
            }
        });
    }
    handleDeleteDevice = () => this.deleteDeviceRequest();

    onHideAddDeviceModal = () => this.setState({ addDeviceModal: { ...this.state.addDeviceModal, show: false }});

    onValidateAddDeviceModal = () => {
        let device = this.state.addDeviceModal.selectedOption;
        device.flavor = device.flavor.id;
        device.operatingSystem = device.operatingSystem.id;
        device.isTemplate = false;

        this.addDeviceRequest(device);
        this.onHideAddDeviceModal();
    }

    onHideDeviceModal = () => this.setState({ deviceModal: { ...this.state.deviceModal, show: false }});

    onValidateDeviceModal = () => {
        let device = this.state.deviceModal.selectedOption;
        device.flavor = device.flavor.id;
        device.operatingSystem = device.operatingSystem.id;
        device.isTemplate = false;

        this.addDeviceRequest(device);
        this.onHideDeviceModal();
    }

    toggleFullscreen = () => this.setState({fullscreen: !this.state.fullscreen});

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
        this.setState({ addDeviceModal: { ...this.state.addDeviceModal, selectedOption }})
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
            this.updateLabRequest(this.labId, {name: val})
                .then(() => this.setState({lab: {...this.state.lab, name: val}}))
            ;
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

    render() {
        return (<>
            <div className="editor-lab-name mb-3">
                <EdiText
                    type='text'
                    value={this.state.lab.name}
                    onSave={this.onNameSave}
                    showButtonsOnHover={true}
                    editOnViewClick={true}
                    mainContainerClassName="editor-title p-2"
                    editButtonContent="✎"
                    editButtonClassName="editor-title-edit-button"
                    submitOnEnter
                />
            </div>
            <div className="editor-wrapper">
                <div id="editor" className={this.getEditorClassNames()}>
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
                    <Modal size="lg" show={this.state.deviceModal.show} onHide={this.onHideDeviceModal}>
                        <Modal.Header closeButton>
                            <Modal.Title>Edit device</Modal.Title>
                        </Modal.Header>
                        <Modal.Body>
                            <DeviceForm device={this.state.deviceModal.device} ref={this.deviceForm} />
                        </Modal.Body>
                    </Modal>
                    <Menu
                        onCreateDeviceRequest={this.handleCreateDevice}
                        onToggleFullscreen={this.toggleFullscreen}
                        onZoomIn={this.onZoomIn}
                        onZoomOut={this.onZoomOut}
                        className="editor-menu"
                    />
                    <Canvas onContextMenu={this.onContextMenu} onZoomIn={this.onZoomIn} onZoomOut={this.onZoomOut} zoom={this.state.canvasZoom}>
                        {this.state.devices.map((device) => (
                            <Device
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
                    <ContextualMenu
                        id="editorContextualMenu"
                        x={this.state.contextualMenu.x}
                        y={this.state.contextualMenu.y}
                        show={this.state.contextualMenu.show}
                    >
                        {this.getContextualMenuItems()}
                    </ContextualMenu>
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
            </div>
        </>)
    }
}