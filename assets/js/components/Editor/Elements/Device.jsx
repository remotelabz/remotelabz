import React from 'react';

export default class Device extends React.Component {
    static defaultProps = {
        x: 0,
        y: 0,
        id: "",
        scale: 1,
        name: "",
    };

    tempX = 0;
    tempY = 0;
    mouseX = 0;
    mouseY = 0;

    constructor(props) {
        super(props);
        this.state = {
            top: this.props.y || 0,
            left: this.props.x || 0,
            dragging: false,
            contextualMenu: null,
        }
    }

    onMouseDown = (e) => {
        this.mouseX = e.clientX;
        this.mouseY = e.clientY;
        this.tempX = this.state.left;
        this.tempY = this.state.top;

        this.setState({
            dragging: true,
        });

        document.onmousemove = this.onDrag;
        document.onmouseup = this.onMouseUp;
    }

    onMouseUp = () => {
        document.onmousemove = null;
        document.onmouseup = null;
        this.setState({dragging: false});
    }

    onDrag = (e) => {
        e.preventDefault();
        const top = (this.tempY + ((e.clientY - this.mouseY) / this.props.scale));
        const left = (this.tempX + ((e.clientX - this.mouseX) / this.props.scale));
        this.setState({
            top,
            left,
        });
        this.props.onUpdate();
    }

    viewportToCanvasPosition(x, y) {
        const canvasPosition = document.getElementById('editorCanvas').getBoundingClientRect();
        const position = {
            x: canvasPosition.left + x,
            y: canvasPosition.top + y,
        }
        return position;
    }

    render() {
        const containerStyle = {
            top: Math.max(this.state.top, 0) + 'px',
            left: Math.max(this.state.left, 0) + 'px',
        }

        return (
            <div
                className="node"
                style={containerStyle}
                id={this.props.id}
            >
                <div className="node-display">
                    <div
                        className={"node-display-inside" + (this.state.dragging ? " dragging" : "")}
                        draggable={true}
                        onMouseDown={this.onMouseDown}
                    />
                </div>
                <div className="node-name unselectable">{this.props.name}</div>
            </div>
        );
    }
}
