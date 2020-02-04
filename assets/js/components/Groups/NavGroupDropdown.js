import React, {Component} from 'react';
import SVG from '../Display/SVG';
import API from '../../api';
import {Tooltip, OverlayTrigger} from 'react-bootstrap';
import Routing from 'fos-jsrouting';

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
                    <div className="text-muted mr-3"><span className="mr-3">
                        {group.children.length > 0 ?
                            <SVG name={this.state.expanded ? "angle-down" : "angle-right"} className="s10"></SVG>
                            :
                            <span className="s10 d-inline-block"> </span>
                        }</span>

                        <SVG name={this.state.expanded ? "folder-open" : "folder-o"}></SVG>
                    </div>

                    <div className={"avatar identicon bg-" + (group.id % 8 + 1) + " s40 rounded-lg mr-3"}>
                        {group.name.charAt(0).toUpperCase()}
                    </div>

                    <div className="d-flex flex-grow-1 flex-basis-0">
                        <div className="fw600 flex-grow-1 flex-basis-0 d-flex flex-column">
                            <div className="d-inline-flex">
                                <a href={Routing.generate('dashboard_show_group', {slug: group.path})} className="fw600 title mr-2" onClick={(e) => e.stopPropagation()}>{ group.name }</a>
                                <div className="text-muted mr-2">
                                    {group.visibility === 0 &&
                                        <div data-toggle="tooltip" data-placement="bottom" title="Private - The group and its activities can only be viewed by yourself.">
                                            <SVG name="lock"></SVG>
                                        </div>
                                    }
                                    {group.visibility === 1 &&
                                        <div data-toggle="tooltip" data-placement="bottom" title="Internal - The group and any internal activities can be viewed by members.">
                                            <SVG name="shield"></SVG>
                                        </div>
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
                                            <SVG name="earth"></SVG>
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
                            <span className="d-inline-flex align-items-center mr-3">
                                <SVG name="folder-o" className="s16"></SVG>
                                <span className="ml-1">{ group.children.length }</span>
                            </span>

                            <span className="d-inline-flex align-items-center mr-3">
                                <SVG name="bookmark" className="s16"></SVG>
                                <span className="ml-1">{ group.activities.length }</span>
                            </span>

                            <span className="d-inline-flex align-items-center mr-3">
                                <SVG name="users" className="s16"></SVG>
                                <span className="ml-1">{ group.users.length }</span>
                            </span>
                        </div>
                    </div>
                    <div className=""></div>
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

export default class GroupDropdown extends Component {
    constructor(props) {
        super(props);
        this.state = {
            search: null,
            loading: true,
        };
    }

    componentDidMount() {
        api.get(this.props.endpoint || Routing.generate('api_get_group', {slug: this.props.path}))
        .then(response => {
            const data = response.data;
            this.setState({data, loading: false});
        });
    }

    render() {
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
                        this.state.data.children.map((group, index) => {
                            return <GroupNode group={group} key={index}></GroupNode>;
                        })
                    }
                </ul>
            </UserContext.Provider>
        );
    }
}