import * as React from 'react';

export default class Canvas extends React.Component {
    constructor(props) {
        super(props);
    }

    onWheel = (e) => {
        if (e.deltaY < 0) {
            this.props.onZoomIn();
        }
        if (e.deltaY > 0) {
            this.props.onZoomOut();
        }
    }

    render() {
        const style = this.props.style || {};
        style.transform = `scale(${this.props.zoom}, ${this.props.zoom})`;
        style.height = (1 / this.props.zoom * 100) + '%';
        style.width = (1 / this.props.zoom * 100) + '%';

        return (
            <div className="editor-canvas-wrapper" onContextMenu={this.props.onContextMenu}>
                <div style={style} id="editorCanvas" className="editor-canvas" onWheel={this.onWheel}>
                    {this.props.children}
                </div>
            </div>
        );
    }
}
