import React, { Component } from 'react';
import { components } from 'react-select';
import Select from 'react-select';

const axios = require('axios').default;

const ValueContainer = ({ children, ...props }) => (
  <components.ValueContainer {...props}>{children}</components.ValueContainer>
);

const options = [{
    value: 'admin',
    label: 'Administrator'
}, {
    value: 'user',
    label: 'User'
}];

export default class GroupRoleSelect extends Component {
    constructor(props) {
        super(props);

        this.state = {
            selectedOption: options.find(el => {
                return el.value === props[0].role;
            })
        }

        //console.log(props);
    }

    handleChange = selectedOption => {
        this.setState(
            { selectedOption }
        );

        console.log(this.props[0]);

        axios.put(`/groups/${this.props[0].group}/user/${this.props[0].user}/role`, {role: selectedOption.value});
    };

    render() {
        const { selectedOption } = this.state;

        return (
            <Select
                options={options}
                value={selectedOption}
                onChange={this.handleChange}
                className='react-select-container'
                classNamePrefix="react-select"
                placeholder="Search for a user"
                name="role"
            />
        );
    }
}