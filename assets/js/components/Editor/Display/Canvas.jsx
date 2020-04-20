import * as React from 'react';

export default class Canvas extends React.Component {
    constructor(props) {
        super(props);
    }

    onWheel = (e) => {
        e.preventDefault();
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
        style.width = "100%";
        style.height = "100%";
        style.height = (1 / this.props.zoom * 100) + '%';
        style.width = (1 / this.props.zoom * 100) + '%';

        return (
            <div style={{flexGrow:2}}>
                <div style={style} id="editorCanvas" className="editor-canvas" onContextMenu={this.props.onContextMenu} onWheel={this.onWheel}>
                    {this.props.children}
                </div>
            </div>
        );
    }
}
