import React, { Component } from 'react';
import { components } from 'react-select';
import AsyncSelect from 'react-select/async';
import API from '../../api';
import SVG from '../Display/SVG';
import Routing from 'fos-jsrouting';
import {Tooltip, OverlayTrigger} from 'react-bootstrap';
import Remotelabz from '../API';

const api = API.getInstance();

const ValueContainer = ({ children, ...props }) => (
  <components.ValueContainer {...props}>{children}</components.ValueContainer>
);

const Option = props => {
    return (
        <components.Option {...props}>
            <div className="d-flex">
                <div className="mr-2">
                    <div className={"avatar identicon bg-" + (props.data.value % 8 + 1) + " s36 rounded mr-2"}>{props.data.label.charAt(0).toUpperCase()}</div>
                </div>
                <div className="d-flex flex-column">
                    <div style={{lineHeight: 16 + 'px'}}>{props.data.fullyQualifiedName} <span className="fw600">{props.data.label}</span></div>
                    {props.data.owner != undefined &&
                        <div>Owned by <img src={"/users/" + props.data.owner.id + "/picture?size=16"} className="rounded-circle"></img> {props.data.owner.name}</div>
                    }
                </div>
                <div className="d-flex flex-grow-1"></div>
                {(props.data.children && props.data.users) &&
                    <div className="d-flex align-items-center">
                        <OverlayTrigger placement="bottom" overlay={<Tooltip>Subgroups</Tooltip>}>
                            <div className="mr-2"><SVG name="folder-o"></SVG> {props.data.children.length}</div>
                        </OverlayTrigger>
                        <OverlayTrigger placement="bottom" overlay={<Tooltip>Members</Tooltip>}>
                            <div><SVG name="users"></SVG> {props.data.users.length}</div>
                        </OverlayTrigger>
                    </div>
                }
            </div>
        </components.Option>
    );
};

const SingleValue = ({ children, ...props }) => (
    <components.SingleValue {...props} className="d-flex align-items-center">
        <div className={"avatar identicon bg-" + (props.data.value % 8 + 1) + " s24 rounded mr-2"} style={{fontSize: 12 + 'px'}}>{props.data.label.charAt(0).toUpperCase()}</div>
        <div>
            {props.data.fullyQualifiedName} <span className="fw600">{props.data.label}</span>
        </div>
    </components.SingleValue>
);

export default class GroupSelect extends Component {
    constructor(props) {
        super(props);
    }

    loadOptions = (input) => {
        return Remotelabz.groups.all(input, 10, 1, false)
        .then(response => {
            const data = []
           // console.log(response.data)
            for (let group of response.data) {
                group.fullyQualifiedName.pop()
                group.fullyQualifiedName = group.fullyQualifiedName.reduce((acc, cur) => acc + cur + ' / ', '')
                if (this.props.exclude && Array.isArray(this.props.exclude)) {
                    if (this.props.exclude.indexOf(group.id) === -1) {
                        data.push({...group, value: group.id, label: group.name});
                    }
                } else {
                    data.push({...group, value: group.id, label: group.name});
                }
            }

            return data;
        });
    }

    render() {
        return (
            <AsyncSelect
                loadOptions={this.props.loadOptions || this.loadOptions}
                className={'react-select-container ' + (this.props.className || "")}
                classNamePrefix="react-select"
                cacheOptions
                defaultOptions={this.props.defaultOptions || true}
                placeholder={this.props.placeholder || "Search for a group"}
                components={{ ValueContainer, Option, SingleValue }}
                isSearchable
                name={this.props.fieldName || "_group"}
                isClearable={this.props.isClearable || false}
                {...this.props}
            />
        );
    }
}