import React from 'react';

export default class DeviceNode extends React.Component {
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

    componentDidMount() {
        this.nodeInsideRef.addEventListener("touchstart", this.onTouchStart, {passive: false});
    }

    componentWillUnmount() {
        this.nodeInsideRef.removeEventListener("touchstart", this.onTouchStart);
    }

    /** @param {TouchEvent} e */
    onTouchStart = (e) => {
        // e.preventDefault();
        this.mouseX = e.touches.item(0).clientX;
        this.mouseY = e.touches.item(0).clientY;
        this.tempX = this.state.left;
        this.tempY = this.state.top;

        this.setState({
            dragging: true,
        });

        window.addEventListener("touchmove", this.onTouchMove, {passive: false});
        window.addEventListener("touchend", this.onTouchEnd, {passive: false});
    }

    /** @param {MouseEvent} e */
    onMouseDown = (e) => {
        e.preventDefault();
        this.mouseX = e.clientX;
        this.mouseY = e.clientY;
        this.tempX = this.state.left;
        this.tempY = this.state.top;

        this.setState({
            dragging: true,
        });

        window.onmousemove = this.onDrag;
        window.onmouseup = this.onMouseUp;
    }

    onTouchEnd = () => {
        window.removeEventListener("touchmove", this.onTouchMove);
        window.removeEventListener("touchend", this.onTouchEnd);
        this.setState({dragging: false});
        if (this.props.onMoved) {
            this.props.onMoved(this);
        }
    }

    onMouseUp = () => {
        window.onmousemove = null;
        window.onmouseup = null;
        this.setState({dragging: false});
        if (this.props.onMoved) {
            this.props.onMoved(this);
        }
    }

    /** @param {TouchEvent} e */
    onTouchMove = (e) => {
        e.preventDefault();
        const top = (this.tempY + ((e.touches.item(0).clientY - this.mouseY) / this.props.scale));
        const left = (this.tempX + ((e.touches.item(0).clientX - this.mouseX) / this.props.scale));
        this.setState({
            top,
            left,
        });
        if (this.props.onDrag) {
            this.props.onDrag(this);
        }
    }

    onDrag = (e) => {
        e.preventDefault();
        const top = (this.tempY + ((e.clientY - this.mouseY) / this.props.scale));
        const left = (this.tempX + ((e.clientX - this.mouseX) / this.props.scale));
        this.setState({
            top,
            left,
        });
        if (this.props.onDrag) {
            this.props.onDrag(this);
        }
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
                        ref={e => this.nodeInsideRef = e}
                        onMouseDown={this.onMouseDown}
                        onTouchStart={this.onTouchStart}
                    />
                </div>
                <div className="node-name unselectable">{this.props.name}</div>
            </div>
        );
    }
}
