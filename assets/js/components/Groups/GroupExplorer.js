import React, {Component} from 'react';
import SVG from '../Display/SVG';
import API from '../../api';
import {Tooltip, OverlayTrigger} from 'react-bootstrap';
import Routing from 'fos-jsrouting';
import GroupPicture from '../Picture/GroupPicture';

const api = API.getInstance();
const UserContext = React.createContext();

class GroupNode extends Component {
    static contextType = UserContext;

    constructor(props) {
        super(props);

        this.state = {expanded: false};
    }

    expand = (e) => {
        e.stopPropagation();
        if (this.props.group.children.length > 0)
            this.setState({expanded: !this.state.expanded});
    }

    render() {
        let group = this.props.group;
        return (
            <li onClick={this.expand}>
                <div className="d-flex align-items-center group-row-contents">
                    <div className="text-muted mr-3">
                        <span className="mr-3">
                            {group.children.length > 0 ?
                                <SVG name={this.state.expanded ? "angle-down" : "angle-right"} className="s10"></SVG>
                                :
                                <span className="s10 d-inline-block"> </span>
                            }
                        </span>

                        <SVG name={this.state.expanded ? "folder-open" : "folder-o"}></SVG>
                    </div>

                    <GroupPicture size={40} group={group} rounded className="mr-3"></GroupPicture>

                    <div className="d-flex flex-grow-1 flex-basis-0">
                        <div className="fw600 flex-grow-1 flex-basis-0 d-flex flex-column">
                            <div className="d-inline-flex">
                                <a href={Routing.generate('dashboard_show_group', {slug: group.path})} className="fw600 title mr-2" onClick={(e) => e.stopPropagation()}>{ group.name }</a>
                                <div className="text-muted mr-2">
                                    {group.visibility === 0 &&
                                        <OverlayTrigger
                                            placement="bottom"
                                            overlay={
                                                <Tooltip id={group.name}>
                                                    Private - The group and its activities can only be viewed by its owner and administrators.
                                                </Tooltip>
                                            }
                                        >
                                            <div>
                                                <SVG name="lock"></SVG>
                                            </div>
                                        </OverlayTrigger>
                                    }
                                    {group.visibility === 1 &&
                                        <OverlayTrigger
                                            placement="bottom"
                                            overlay={
                                                <Tooltip id={group.name}>
                                                    Internal - The group and any internal activities can be viewed by members.
                                                </Tooltip>
                                            }
                                        >
                                            <div>
                                                <SVG name="shield"></SVG>
                                            </div>
                                        </OverlayTrigger>
                                    }
                                    {group.visibility === 2 &&
                                        <OverlayTrigger
                                            placement="bottom"
                                            overlay={
                                                <Tooltip id={group.name}>
                                                    Public - The group and any internal projects can be viewed by any logged in user.
                                                </Tooltip>
                                            }
                                        >
                                            <div>
                                                <SVG name="earth"></SVG>
                                            </div>
                                        </OverlayTrigger>
                                    }
                                </div>
                            {group.owner.email === this.context &&
                                <div className="group-user-role">Owner</div>
                            }
                            </div>
                            { group.description !== undefined &&
                                <div className="group-description overflow-hidden flex-grow-1" style={{maxHeight: 24 + 'px'}}>
                                    {group.description}
                                </div>
                            }
                        </div>

                        <div className="d-flex flex-grow-0 align-items-center text-muted">

                          
                        </div>
                    </div>
                </div>

                <ul className="content-list group-list-tree" style={{display: this.state.expanded ? 'block' : 'none'}}>
                    {group.children.map((group, index) => {
                        return <GroupNode group={group} key={index}></GroupNode>;
                    })}
                </ul>
            </li>
        )
    }
}

export default class GroupExplorer extends Component {
    constructor(props) {
        super(props);
        this.state = {
            data: {
                children: [],
            },
            loading: true,
        };
    }

    componentDidMount() {
        api.get(this.props.endpoint || Routing.generate('api_get_group', {slug: this.props.path}), {
            params: {
                context: 'group_tree',
                root_only: true
            }
        })
        .then(response => {
            const data = response.data;
            this.setState({data, loading: false});
        });
    }

    getRootNodes() {
        return Array.isArray(this.state.data) ? this.state.data : this.state.data.children;
    }

    render() {
        const nodes = this.getRootNodes();
        return (
            <UserContext.Provider value={this.props.user}>
                <ul className="labs-panel content-list p-0 list-unstyled group-list-tree">
                    {this.state.loading ?
                        <div className="d-flex align-items-center justify-content-center py-4">
                            <div className="mr-2">
                                <i className="fas fa-circle-notch fa-spin"></i>
                            </div>
                            Loading...
                        </div>
                    :
                        nodes.map((group, index) => {
                            return <GroupNode group={group} key={index}></GroupNode>;
                        })
                    }
                </ul>
            </UserContext.Provider>
        );
    }
}