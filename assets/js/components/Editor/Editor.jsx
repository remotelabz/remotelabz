import React, {useState} from 'react';
import { Modal, Button, Accordion, Card, Form } from 'react-bootstrap';
import Menu from './Menu/Menu';
import ContextualMenu from './Menu/ContextualMenu';
import Canvas from './Display/Canvas';
import DeviceNode from './Elements/DeviceNode';
import { jsPlumb } from 'jsplumb';
import SVG from '../Display/SVG';
import API from '../../api';
import DeviceTemplateSelect from './Form/DeviceTemplateSelect';
import Skeleton from 'react-loading-skeleton';
import SimpleMDE from "react-simplemde-editor";
import ReactMarkdown from 'react-markdown';
import Remotelabz, { Device } from '../API';
import DeviceAsideMenu from './Menu/DeviceAsideMenu';
import LabAsideMenu from './Menu/LabAsideMenu';
import LabEditorHeader from './Elements/LabEditorHeader';

export default class Editor extends React.Component {
    jsPlumb = null;
    /** @var {number} labId */
    labId = this.props.id;
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
            lab: {
                name: null,
                id: this.props.id
            },
            /** @type {import('../API').Device[]} devices */
            devices: [],
            isLoading: false,
            asideMenu: null,
        }
    }

    componentDidMount() {
        this.labId = this.props.id;

        this.reloadLab(this.labId);
    }

    reloadLab = async id => {
        const lab = (await Remotelabz.labs.get(id)).data
        let devices = []
        for (const _device of lab.devices) {
            const device = await Remotelabz.devices.get(_device.id)
            devices.push(device.data)
        }
        this.setState({ devices, lab, ready: true })
    }

    /** @param {Device} device */
    addDeviceRequest = device => {
        //device.networkInterfaces = null
        device.lab = this.labId
        Remotelabz.labs.addDeviceInLab(this.labId,device).then(response => {
            this.addDevice(response.data)
        })

    }

    updateDevicePositionRequest = device => {
        Remotelabz.devices.updatePosition(device.props.id, device.state.left, device.state.top)
    }

    deleteDeviceRequest = () => {
        Remotelabz.devices.delete(this.state.contextualMenu.target.id).then(() => {
            const devices = this.state.devices.filter((device) => {
                return device.id != this.state.contextualMenu.target.id;
            });
            this.setState({ devices });
        })
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
        let device = this.state.devices.find(device => {
            return device.id == this.state.contextualMenu.target.id;
        });
        this.setState({
            editDeviceForm: {
                ...this.state.editDeviceForm,
                device: device.id,
                show: true,
            },
        });
        this.getAsideMenuChildren('device', device.id);
    }

    onSubmitDeviceForm = () => {
        //console.log("onSubmitDeviceForm")
        this.reloadLab(this.labId);
    }

    onSubmitLabForm = lab => {
        this.setState({
            isLoading: true,
        });
        Remotelabz.labs.update({
            id: lab.id,
            fields: lab
        })
        .then(response => {
            this.hideAsideMenu();
            this.setState({
                lab: response.data
            });
        })
        .finally(() => this.setState({ isLoading: false }));
    }

    handleDeleteDevice = () => {
        this.hideAsideMenu();
        this.deleteDeviceRequest();
    }

    onHideAddDeviceModal = () => this.setState({ addDeviceModal: { ...this.state.addDeviceModal, show: false } });

    onValidateAddDeviceModal = () => {
        let device = this.state.addDeviceModal.selectedOption;
        //console.log("Before");
        //console.log(device);
        
        let networkInterfaces = [];

        device.flavor = device.flavor.id;
        device.operatingSystem = device.operatingSystem.id;
        device.hypervisor = device.hypervisor.id;
        device.isTemplate = false;
        device.networkInterfaces.forEach(element => networkInterfaces.push(element.id));
        //device.networkInterfaces.forEach(element => console.log(element.id));
        device.networkInterfaces = networkInterfaces;
        //console.log("After");
        //console.log(device);

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

    onLabEdit = lab => this.getAsideMenuChildren('lab', lab.id);

    onNameSave = val => {
        if (val != this.state.lab.name) {
            Remotelabz.devices.update(this.labId, { name: val })
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

    getAsideMenuChildren = async (type, id = 0) => {
        switch (type) {
            case 'lab':
                const lab = this.state.lab;
                this.setState({
                    asideMenu: (
                        <LabAsideMenu
                            lab={lab}
                            onClose={this.hideAsideMenu}
                            onSubmitLabForm={this.onSubmitLabForm}
                        />
                    )
                });
                break;

            case 'device':
                this.setState({
                    asideMenu: (
                        <DeviceAsideMenu
                            device={id}
                            onClose={this.hideAsideMenu}
                            onSubmitDeviceForm={this.onSubmitDeviceForm}
                        />
                    )
                });
                break;
            
            default:
                this.setState({ asideMenu: null });
                break;
        }
    }

    hideAsideMenu = () => this.getAsideMenuChildren(null);

    render() {
        return (<>
            {this.state.ready ?
                <div>
                    <div className="editor-lab-name mb-3">
                        <LabEditorHeader id={this.state.lab.id} initialName={this.state.lab.name} />
                    </div>
                    <div className="mb-3">
                        <div className="mb-3">
                            <ShortDescriptionEditor id={this.state.lab.id} initialValue={this.state.lab.shortDescription} onChange={(isLoading) => this.setState({isLoading})} />
                        </div>

                        <DescriptionEditor id={this.state.lab.id} initialValue={this.state.lab.description} />
                    </div>
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
                                onLabEditClick={this.onLabEdit}
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
                                {this.state.asideMenu}
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
                    </div>
                </div>
            :
                <Skeleton height={800} />
            }
        </>)
    }
}

function ShortDescriptionEditor({ id, initialValue, onChange }) {
    const handleChange = (e) => {
        e.persist();

        const fields = {
            shortDescription: e.target.value
        }

        setTimeout(() => {
            onChange && onChange(true);
            Remotelabz.labs.update({ id, fields })
                .finally(() => onChange && onChange(false));
        }, 100);
    }

    return (<Form.Control
        name="shortDescription"
        type="text"
        placeholder="Write a small description about your lab (255 characters max.)"
        maxLength={255}
        onChange={handleChange}
        defaultValue={initialValue}
        />)
}

function DescriptionEditor({ id, initialValue }) {
    const [description, setDescription] = useState(initialValue);
    const [isEditing, setEditing] = useState(false);

    const onSave = () => {
        const params = {
            id,
            fields: {
                description
            }
        }
        Remotelabz.labs.update(params)
        .then(() => setEditing(false));
    }

    return (
        <div>
            { isEditing ?
                <div>
                    <DescriptionMarkdownEditor description={description} onChange={setDescription} />
                    <div className="d-flex">
                        <Button variant="success" onClick={onSave}>Save</Button>
                        <div className="flex-grow-1" />
                        <Button variant="default" onClick={() => setEditing(false)}>Cancel</Button>
                    </div>
                </div>
            :
                <DescriptionDisplayer description={description} onEdit={() => setEditing(true)} />
            }
        </div>
    )
}

function DescriptionMarkdownEditor({ description, onChange }) {
    return <SimpleMDE value={description} onChange={onChange} />
}

function DescriptionDisplayer({ description, onEdit }) {
    return (
        <Accordion className="lab-description">
            <Card>
                <Accordion.Toggle as={Card.Header} variant="link" eventKey="0">
                    <div>
                        <SVG name="text-description" className="v-sub s18 mr-2" /> Content
                </div>
                    <div>
                        <Button variant="default" onClick={onEdit}><SVG name="pencil" /></Button>
                    </div>
                </Accordion.Toggle>
                <Accordion.Collapse eventKey="0">
                    <Card.Body>
                        <div className="text-muted">
                            <ReactMarkdown source={description || "Content is empty"} />
                        </div>
                    </Card.Body>
                </Accordion.Collapse>
            </Card>
        </Accordion>
    )
}