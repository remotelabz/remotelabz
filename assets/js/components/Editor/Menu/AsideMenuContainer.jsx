import React from 'react';
import SVG from '../../Display/SVG';
import { Button, OverlayTrigger, Tooltip } from 'react-bootstrap';

export default function AsideMenuContainer(props) {
    return (
        <div style={{ overflowY: 'scroll' }}>
            <aside className="editor-aside-toolbar">
                <OverlayTrigger placement="top" overlay={<Tooltip>Close side menu</Tooltip>}>
                    <Button variant="danger" onClick={props.onClose} className="float-right">
                        <SVG name="close" className="image-sm v-sub"></SVG>
                    </Button>
                </OverlayTrigger>
                {props.children}
            </aside>
        </div>
    );
}
