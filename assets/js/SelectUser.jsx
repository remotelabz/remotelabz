import React, { Component } from 'react';
import ReactDOM from 'react-dom';
import { components } from 'react-select';
import AsyncSelect from 'react-select/async';

const axios = require('axios').default;

const ValueContainer = ({ children, ...props }) => (
  <components.ValueContainer {...props}>{children}</components.ValueContainer>
);

const Option = props => {
    console.log(props);
    return (
        <components.Option {...props}>
            <div className="d-flex">
                <div className="mr-2">
                    <img src={"/users/" + props.data.id + "/picture?size=32"} className="rounded-circle"></img>
                </div>
                <div className="d-flex flex-column">
                    <div style={{lineHeight: 16 + 'px'}}>{props.label}</div>
                    <div className="text-muted">{props.data.email}</div>
                </div>
            </div>
        </components.Option>
    );
};

export default class CustomControl extends Component {
    constructor(props) {
        super(props);
    }

    loadOptions = (inputValue) => {
        return axios.get('/api/users', {
            params: {
                search: inputValue
            }
        })
        .then(response => {
            let options = [];
            response.data.forEach(user => {
                options.push({
                    ...user,
                    value: user.id,
                    label: user.name,
                })
            });
            return options;
        });
    }

    render() {
        return (
            <AsyncSelect
                styles={{
                    singleValue: base => ({ ...base }),
                    valueContainer: base => ({
                        ...base,
                        width: '100%',
                    }),
                    option: base => ({
                        ...base,
                        border: `none`,
                        height: '100%',
                    }),
                }}
                isMulti
                closeMenuOnSelect={false}
                loadOptions={this.loadOptions}
                cacheOptions
                defaultOptions
                placeholder="Search for a user"
                components={{ ValueContainer, Option }}
                isSearchable
                name="color"
                // options={
                //     [
                //         { value: 'orange', label: 'Orange', color: '#FF8B00' },
                //         { value: 'yellow', label: 'Yellow', color: '#FFC400' },
                //     ]
                // }
            />
        );
    }
}

ReactDOM.render(
    <CustomControl></CustomControl>,
    document.getElementById('usersSelect')
);