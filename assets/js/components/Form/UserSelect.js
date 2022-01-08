import React, { Component } from 'react';
import { components } from 'react-select';
import AsyncSelect from 'react-select/async';
import Remotelabz from '../API';

export const ValueContainer = ({ children, ...props }) => (
  <components.ValueContainer {...props}>{children}</components.ValueContainer>
);

export const Option = props => {
    return (
        <components.Option {...props}>
            <div className="d-flex">
                <div className="mr-2">
                    <img src={"/users/" + props.data.id + "/picture?size=32"} className="rounded-circle"></img>
                </div>
                <div className="d-flex flex-column">
                    <div style={{lineHeight: 16 + 'px'}}>{props.label}</div>
                    {/* <div className="text-muted">{props.data.email}</div> */}

                </div>
            </div>
        </components.Option>
    );
};

export default class UserSelect extends Component {
    constructor(props) {
        super(props);
    }

    loadOptions = async (inputValue) => (await Remotelabz.users.all(inputValue)).data;

    render() {
        //console.log(this.props)
        return (
            <AsyncSelect
                isMulti
                closeMenuOnSelect={false}
                loadOptions={this.loadOptions}
                getOptionLabel={o => o.name}
                getOptionValue={o => o.id}
                className='react-select-container'
                classNamePrefix="react-select"
                cacheOptions
                defaultOptions
                placeholder="Search for a user"
                components={{ ValueContainer, Option }}
                isSearchable
                name="users[]"
                {...this.props}
            />
        );
    }
}