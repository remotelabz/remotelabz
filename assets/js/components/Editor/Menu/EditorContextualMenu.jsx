import React, { Component } from 'react';
import { ListGroup, ButtonToolbar } from 'react-bootstrap';
import PropTypes from 'prop-types';

export default class EditorContextualMenu extends Component
{
    propTypes = {
        x: PropTypes.number.isRequired,
        y: PropTypes.number.isRequired,
    }

    constructor(props)
    {
        super(props);

        this.state = {
            x: props.x,
            y: props.y,
        }
    }

    handleDeleteItem = e => {
        console.log("User clicked on delete.");
    }

    render()
    {
        return (
            <ListGroup style={{top: this.state.y, left: this.state.x}} as="ul" className="editor-contextual-menu">
                <ListGroup.Item as="li" action className="editor-contextual-menu-option" onClick={this.handleDeleteItem}>Delete</ListGroup.Item>
            </ListGroup>
        );
    }
}