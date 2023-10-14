import React, { Component } from 'react';
import Select from 'react-select';
import Noty from 'noty';
import API from '../../api';

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
                return el.value === props.role;
            }),
            isLoading: false
        }

        //console.log(props);
    }

    handleChange = selectedOption => {
        this.setState({
            isLoading: true
        });

        API.getInstance().put(`/api/groups/${this.props.group}/user/${this.props.user}/role`, {role: selectedOption.value})
        .then(() => {
            this.setState({ selectedOption });
            new Noty({
                text: "User's role has been changed.",
                type: "success",
                timeout: 2000
            }).show();
        })
        .catch(() => {
            // console.log(error);
            new Noty({
                text: "There was an error changing user's role. Please try again later.",
                type: "error",
                timeout: 2000
            }).show();
        })
        .finally(() => {
            this.setState({isLoading: false});
            $.ajax({
                type: "GET",
                url: `/api/groups/${this.props.group}/members/${this.props.user}`,
                success: function (response) {
                    $(`#${response.data.user}-badges`).html(response.data.html);              
                }  
            });
        });
    };

    /**
     * @param {string} group Group's slug 
     * @param {number} user User ID
     * @param {string} role Role descriptor
     */
    changeRoleRequest(group, user, role) {
        return axios.put(`/api/groups/${group}/user/${user}/role`, {role});
    }

    render() {
        const { selectedOption } = this.state;

        return (
            <Select
                isDisabled={this.state.isLoading}
                isLoading={this.state.isLoading}
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