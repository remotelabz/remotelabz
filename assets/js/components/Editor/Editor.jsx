import React from 'react';
import EditorMenu from './Menu/EditorMenu';
import EditorContextualMenu from './Menu/EditorContextualMenu';
import EditorCanvas from './Display/EditorCanvas';
import DeviceModal from './Modal/DeviceModal';
import Device from './Elements/Device';

export default class Editor extends React.Component {
    constructor(props) {
        super(props);

        this.devices = [
            {name: 'test', id: "test", x: 100, y: 100},
            {name: 'test2', id: "test2"},
        ]

        this.state = {
            showDeviceModal: false,
            contextualMenu: null,
            canvasZoom: 1,
            fullscreen: false,
        }
    }

    handleCreateDevice = () => this.setState({ showDeviceModal: true });

    /** @param {Device} device */
    onSaveDeviceModal = (device) => {
        this.addDevice(device);
        this.onHideDeviceModal();
    }
    onHideDeviceModal = () => this.setState({ showDeviceModal: false });

    /**
     * Add a device to the device list.
     *
     * @param {Device} device
     * @memberof Editor
     */
    addDevice = (device) => {
        this.devices.push({
            name: device.name,
            id: device.name + (this.devices.length + 1).toString(),
        })
    }

    
    toggleFullscreen = () => this.setState({fullscreen: !this.state.fullscreen});

    onCanvasScale = (canvasZoom) => this.setState({canvasZoom});
    
    /**
     * @param {React.MouseEvent} e
     */
    onContextMenu = (e) => {
        e.preventDefault();
        const position = e.target.getBoundingClientRect();
        console.log(position);
        this.setState({
            contextualMenu: {
                y: position.top + (e.clientY - position.top),
                x: position.left + (e.clientX - position.left),
            },
        })
        document.onmousedown = this.onClickWhileOpenContextMenu;
    }

    /**
     * @param {React.MouseEvent} e
     */
    onClickWhileOpenContextMenu = (e) => {
        console.log(e.target.closest('.editor-contextual-menu'));
        if (e.target.closest('.editor-contextual-menu') === null) {
            this.setState({
                contextualMenu: null,
            });

            document.onmousedown = null;
        }
    }

    EditorContextualMenu = () => {
        if (!this.state.contextualMenu) {
            return null;
        }

        const x = this.state.contextualMenu.x;
        const y = this.state.contextualMenu.y;

        return <EditorContextualMenu x={x} y={y} id="editorContextualMenu" />;
    }

    render() {
        return (
            <div id="editor" className={"editor" + (this.state.fullscreen ? " fullscreen" : "")}>
                <DeviceModal show={this.state.showDeviceModal} onHide={this.onHideDeviceModal} onSave={this.onSaveDeviceModal}></DeviceModal>
                <EditorMenu onCreateDeviceRequest={this.handleCreateDevice} onToggleFullscreen={this.toggleFullscreen} className="editor-menu"></EditorMenu>
                <EditorCanvas devices={this.devices} onScale={this.onCanvasScale} onContextMenu={this.onContextMenu}>
                </EditorCanvas>
                <this.EditorContextualMenu></this.EditorContextualMenu>
                <div className="editor-toolbar-wrapper">
                    <span className="text-muted">Zoom : {Math.round(this.state.canvasZoom * 100)}%</span>
                </div>
            </div>
        )
    }
}