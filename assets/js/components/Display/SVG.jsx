import React, { Component } from 'react';
import PropTypes from 'prop-types';

export default class SVG extends Component {
    constructor(props) {
        super(props);
    }

    render() {
        return (
            <svg className={this.props.className || 'image-sm'}>
                <use xlinkHref={"/build/svg/icons.svg#" + this.props.name}></use>
            </svg>
        )
    }
}
