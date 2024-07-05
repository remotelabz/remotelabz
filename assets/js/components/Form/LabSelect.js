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
                <div className="d-flex flex-column">
                    <div>{props.label}</div>
                </div>
            </div>
        </components.Option>
    );
};

export default class LabSelect extends Component {
    constructor(props) {
        super(props);
    }

    loadOptions = async (inputValue) => (await Remotelabz.labs.all(inputValue, 10)).data;

    render() {
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
                placeholder="Search for a lab"
                components={{ ValueContainer, Option }}
                isSearchable
                name="labs[]"
            />
        );
    }
}