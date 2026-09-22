import React, { useState, useEffect, useRef } from 'react';
import { Modal, Button, Spinner } from 'react-bootstrap';
import { toast } from 'react-toastify';
import Remotelabz from '../API';
import SVG from '../Display/SVG';

const PRESENCE_TIMEOUT = 60 * 1000;
const PRESENCE_INTERVAL = 30 * 1000;
const ROOM_REFRESH_INTERVAL = 50 * 60 * 1000;

/**
 * Real-time chat window for the users of a group executing a lab.
 *
 * Subscribes to the Mercure hub (SSE, same origin on /mercure/) using the
 * mercureAuthorization cookie set by GET /api/chat/{labUuid}/room.
 */
function ChatWindow({ show, onHide, lab, user }) {
    const [room, setRoom] = useState(null);
    const [members, setMembers] = useState([]);
    const [messages, setMessages] = useState([]);
    const [inputValue, setInputValue] = useState('');
    const [error, setError] = useState(null);
    const [connected, setConnected] = useState(false);
    const [loading, setLoading] = useState(true);
    const [now, setNow] = useState(Date.now());

    const eventSourceRef = useRef(null);
    const heartbeatRef = useRef(null);
    const roomRefreshRef = useRef(null);
    const presenceRef = useRef({});
    const messagesEndRef = useRef(null);
    const myUuid = user.uuid;

    const isOnline = (uuid) => {
        const lastSeen = presenceRef.current[uuid];
        return lastSeen !== undefined && (now - lastSeen) < PRESENCE_TIMEOUT;
    };

    const sendPresence = (action) => {
        Remotelabz.chat.sendPresence(lab.uuid, action).catch(() => {});
    };

    const handleEvent = (event) => {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch (e) {
            return;
        }

        if (data.type === 'message') {
            setMessages(prev => prev.some(m => m.id === data.id) ? prev : [...prev, data]);
        } else if (data.type === 'presence') {
            if (data.action === 'leave') {
                delete presenceRef.current[data.uuid];
            } else {
                presenceRef.current[data.uuid] = Date.now();
            }
            setNow(Date.now());
        }
    };

    useEffect(() => {
        if (!show) return undefined;

        let cancelled = false;

        const init = async () => {
            try {
                const response = await Remotelabz.chat.room(lab.uuid);
                if (cancelled) return;

                const data = response.data;
                setRoom(data);
                setMembers(data.members);
                setError(null);

                const history = await Remotelabz.chat.messages(lab.uuid, null, 50);
                if (cancelled) return;
                setMessages(history.data);
                setLoading(false);

                const eventSource = new EventSource('/mercure?match=' + encodeURIComponent(data.topic));
                eventSourceRef.current = eventSource;
                eventSource.onopen = () => { if (!cancelled) setConnected(true); };
                eventSource.onerror = () => { if (!cancelled) setConnected(false); };
                eventSource.onmessage = handleEvent;

                sendPresence('join');
                heartbeatRef.current = setInterval(() => sendPresence('join'), PRESENCE_INTERVAL);
                roomRefreshRef.current = setInterval(async () => {
                    try {
                        const fresh = await Remotelabz.chat.room(lab.uuid);
                        if (!cancelled) setMembers(fresh.data.members);
                    } catch (e) {
                        // The room cookie expired or the membership changed:
                        // the EventSource reconnection will surface the error.
                    }
                }, ROOM_REFRESH_INTERVAL);
            } catch (err) {
                if (!cancelled) {
                    setError(err.response?.data?.message || 'You cannot access the chat of this lab.');
                    setLoading(false);
                }
            }
        };

        const handleBeforeUnload = () => sendPresence('leave');
        window.addEventListener('beforeunload', handleBeforeUnload);

        init();

        return () => {
            cancelled = true;
            sendPresence('leave');
            window.removeEventListener('beforeunload', handleBeforeUnload);
            if (eventSourceRef.current) {
                eventSourceRef.current.close();
                eventSourceRef.current = null;
            }
            if (heartbeatRef.current) clearInterval(heartbeatRef.current);
            if (roomRefreshRef.current) clearInterval(roomRefreshRef.current);
            setConnected(false);
            setLoading(true);
        };
    }, [show, lab.uuid]);

    // Re-render periodically so stale presence dots turn off
    useEffect(() => {
        const tick = setInterval(() => setNow(Date.now()), 15 * 1000);
        return () => clearInterval(tick);
    }, []);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, connected]);

    const onSubmit = async (e) => {
        e.preventDefault();
        const message = inputValue.trim();
        if (!message) return;

        setInputValue('');
        try {
            const response = await Remotelabz.chat.sendMessage(lab.uuid, message);
            setMessages(prev => prev.some(m => m.id === response.data.id) ? prev : [...prev, response.data]);
        } catch (err) {
            setInputValue(message);
            toast.error('An error happened while sending your message. Please try again.', { autoClose: 10000 });
        }
    };

    const onlineCount = members.filter(m => isOnline(m.uuid)).length;

    return (
        <Modal show={show} onHide={onHide} size="lg" backdrop="static">
            <Modal.Header closeButton>
                <Modal.Title>
                    <SVG name="comment" className="v-sub image-sm"></SVG>
                    <span className="ml-2">Chat { room && <span className="text-muted">— {room.group.name}</span> }</span>
                    <span className={`ml-2 small ${connected ? 'text-success' : 'text-danger'}`}>
                        {connected ? 'Connected' : 'Reconnecting...'}
                    </span>
                </Modal.Title>
            </Modal.Header>
            <Modal.Body>
                {error
                    ?
                    <div className="text-danger py-5 text-center">{error}</div>
                    :
                    <div className="d-flex" style={{ height: 480 }}>
                        {/* Participants */}
                        <div className="border-right pr-3" style={{ width: 220 }}>
                            <div className="text-muted small mb-2">
                                Participants ({onlineCount} online / {members.length})
                            </div>
                            {loading
                                ?
                                <Spinner animation="border" size="sm" className="my-3" />
                                :
                                members.length === 0
                                    ?
                                    <div className="text-muted small">Nobody is executing this lab yet.</div>
                                    :
                                    members.map(member => (
                                        <div key={member.uuid} className="d-flex align-items-center mb-2" title={member.email}>
                                            <span
                                                style={{
                                                    display: 'inline-block',
                                                    width: 8,
                                                    height: 8,
                                                    borderRadius: '50%',
                                                    marginRight: 8,
                                                    backgroundColor: isOnline(member.uuid) ? '#28a745' : '#adb5bd'
                                                }}
                                            ></span>
                                            <span className="small text-truncate">
                                                {member.name}{member.uuid === myUuid ? ' (you)' : ''}
                                            </span>
                                        </div>
                                    ))
                            }
                        </div>

                        {/* Messages */}
                        <div className="d-flex flex-column pl-3 flex-grow-1">
                            <div className="flex-grow-1 overflow-auto pr-1" style={{ minHeight: 320 }}>
                                {messages.length === 0
                                    ?
                                    <div className="text-muted text-center mt-5">
                                        No messages yet. Say hello!
                                    </div>
                                    :
                                    messages.map(message => (
                                        <div key={message.id} className="mb-3">
                                            <strong className="small">{message.name}</strong>
                                            <span className="text-muted small ml-2">
                                                {new Date(message.createdAt).toLocaleTimeString()}
                                            </span>
                                            <div className="small" style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{message.message}</div>
                                        </div>
                                    ))
                                }
                                <div ref={messagesEndRef}></div>
                            </div>
                            <form onSubmit={onSubmit} className="d-flex mt-2">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="Type a message..."
                                    value={inputValue}
                                    maxLength={2000}
                                    onChange={e => setInputValue(e.target.value)}
                                    disabled={!connected}
                                />
                                <Button type="submit" variant="primary" className="ml-2" disabled={!connected || inputValue.trim() === ''}>
                                    Send
                                </Button>
                            </form>
                        </div>
                    </div>
                }
            </Modal.Body>
        </Modal>
    )
}

export default ChatWindow;
