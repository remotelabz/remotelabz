import * as React from 'react';
import Device from '../Elements/Device';
import { jsPlumb } from 'jsplumb';
import PropTypes from 'prop-types';

export default class EditorCanvas extends React.Component {
    zoomMin = 0.5;
    zoomMax = 2;
    jsPlumb = null;

    propTypes = {
        onScale: PropTypes.func,
        style: PropTypes.object,
        devices: PropTypes.arrayOf(Device),
        onContextMenu: PropTypes.func,
    }

    constructor(props) {
        super(props);

        this.jsPlumb = jsPlumb.getInstance({
            Endpoint: "Blank",
        });
        this.state = {
            zoom: 1,
        }
    }

    // onMouseDown = (e) => {
    //     this.setState({
    //         mouseX: e.clientX,
    //         mouseY: e.clientY,
    //         tempX: e.target.getBoundingClientRect().left,
    //         tempY: e.target.getBoundingClientRect().top,
    //         dragging: true
    //     });
    //     document.onmousemove = this.onDrag;
    //     document.onmouseup = this.onMouseUp;
    // }

    // onMouseUp = () => {
    //     document.onmousemove = null;
    //     document.onmouseup = null;
    //     this.state.top < 0 && this.setState({top: 0});
    //     this.state.left < 0 && this.setState({left: 0});
    //     this.setState({dragging: false});
    // }

    // onDrag = (e) => {
    //     e.preventDefault();
    //     let canvas = document.getElementById('editorCanvas').getBoundingClientRect();
    //     let top = this.state.tempY + (e.clientY - this.state.mouseY) - canvas.top;
    //     let left = this.state.tempX + (e.clientX - this.state.mouseX) - canvas.left;
    //     this.setState({
    //         top: top,
    //         left: left
    //     });
    //     jsPlumb.repaintEverything();
    // }

    onWheel = (e) => {
        let zoom = this.state.zoom;
        if (e.deltaY < 0 && zoom < this.zoomMax) {
            zoom += 0.1;
        } else if (e.deltaY > 0 && zoom > this.zoomMin) {
            zoom -= 0.1;
        }

        this.setState({
            zoom,
        });
        this.props.onScale(zoom);
    }

    componentDidMount() {
        this.jsPlumb.ready(() => {
            this.jsPlumb.connect({
                source: 'test',
                target: 'test2',
                connector: "Straight",
                anchor: "Center",
                endpoint: "Blank",
            });
        })
    }

    componentDidUpdate = () => this.jsPlumb.repaintEverything();
    onDeviceUpdate = () => this.jsPlumb.repaintEverything();

    render() {
        const style = this.props.style || {};
        style.transform = `scale(${this.state.zoom}, ${this.state.zoom})`;
        style.height = (1 / this.state.zoom * 100) + '%';
        style.width = (1 / this.state.zoom * 100) + '%';

        const deviceList = this.props.devices.map((device, index) => (
            <Device
                key={index}
                name={device.name}
                id={device.id}
                x={device.x}
                y={device.y}
                scale={this.state.zoom}
                onUpdate={this.onDeviceUpdate}
            />
        ));

        return (
            <div className="editor-canvas-wrapper" onContextMenu={this.props.onContextMenu}>
                <div style={style} id="editorCanvas" className="editor-canvas" onWheel={this.onWheel}>
                    {deviceList}
                </div>
            </div>
        );
    }
}
