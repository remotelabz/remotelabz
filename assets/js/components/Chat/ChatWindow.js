import React, { useState, useEffect, useRef } from 'react';
import { Modal, Button, Spinner } from 'react-bootstrap';
import { toast } from 'react-toastify';
import Remotelabz from '../API';
import SVG from '../Display/SVG';

const PRESENCE_TIMEOUT = 60 * 1000;
const PRESENCE_INTERVAL = 30 * 1000;
const ROOM_REFRESH_INTERVAL = 50 * 60 * 1000;

const FLOATING_MIN_WIDTH = 320;
const FLOATING_MIN_HEIGHT = 240;
const FLOATING_DEFAULT_WIDTH = 460;
const FLOATING_DEFAULT_HEIGHT = 520;

/**
 * Real-time chat window for the users of a group executing a lab.
 *
 * Subscribes to the Mercure hub (SSE, same origin on /mercure/) using the
 * __Secure-mercure_access_token cookie set by GET /api/chat/{labUuid}/room.
 *
 * Display modes:
 *  - variant "modal" (default): react-bootstrap modal, optionally detachable
 *    into a floating panel (draggable, resizable, minimizable).
 *  - variant "standalone": full-page chat, meant for the separate browser
 *    window served at /labs/chat/{labUuid}.
 */
function ChatWindow({ show, onHide, lab, user, variant = 'modal' }) {
    const [room, setRoom] = useState(null);
    const [members, setMembers] = useState([]);
    const [messages, setMessages] = useState([]);
    const [inputValue, setInputValue] = useState('');
    const [error, setError] = useState(null);
    const [connected, setConnected] = useState(false);
    const [loading, setLoading] = useState(true);
    const [now, setNow] = useState(Date.now());

    const [detached, setDetached] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const [unread, setUnread] = useState(0);
    const [pos, setPos] = useState(() => ({
        x: Math.max(16, window.innerWidth - FLOATING_DEFAULT_WIDTH - 24),
        y: 96,
    }));
    const [size, setSize] = useState({ w: FLOATING_DEFAULT_WIDTH, h: FLOATING_DEFAULT_HEIGHT });

    const eventSourceRef = useRef(null);
    const heartbeatRef = useRef(null);
    const roomRefreshRef = useRef(null);
    const presenceRef = useRef({});
    const messagesEndRef = useRef(null);
    // Mirrors detached/minimized for the (stable) SSE handler closure.
    const displayRef = useRef({ detached: false, minimized: false });
    const myUuid = user.uuid;

    useEffect(() => {
        displayRef.current = { detached, minimized };
    }, [detached, minimized]);

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
            if (displayRef.current.detached && displayRef.current.minimized) {
                setUnread(u => u + 1);
            }
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

    const startDrag = (e, mode, origin, setter) => {
        e.preventDefault();
        const startX = e.clientX;
        const startY = e.clientY;
        const onMove = (ev) => {
            if (mode === 'move') {
                setter({
                    x: Math.min(Math.max(origin.x + ev.clientX - startX, 0), window.innerWidth - 120),
                    y: Math.min(Math.max(origin.y + ev.clientY - startY, 0), window.innerHeight - 48),
                });
            } else {
                setter({
                    w: Math.max(FLOATING_MIN_WIDTH, origin.w + ev.clientX - startX),
                    h: Math.max(FLOATING_MIN_HEIGHT, origin.h + ev.clientY - startY),
                });
            }
        };
        const onUp = () => {
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
        };
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
    };

    const openInWindow = () => {
        window.open('/labs/chat/' + lab.uuid, 'remotelabz_chat_' + lab.uuid, 'width=420,height=640');
    };

    const restoreFromMinimized = () => {
        setMinimized(false);
        setUnread(0);
    };

    const onlineCount = members.filter(m => isOnline(m.uuid)).length;

    const statusBadge = (
        <span className={`ml-2 small ${connected ? 'text-success' : 'text-danger'}`}>
            {connected ? 'Connected' : 'Reconnecting...'}
        </span>
    );

    const renderBody = (height) => (
        error
            ?
            <div className="text-danger py-5 text-center">{error}</div>
            :
            <div className="d-flex" style={{ height }}>
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
    );

    if (variant === 'standalone') {
        return (
            <div className="rlz-chat-panel" style={{ height: '100vh', display: 'flex', flexDirection: 'column' }}>
                <div className="rlz-chat-panel-header" style={{ padding: '8px 12px', borderBottom: '1px solid', display: 'flex', alignItems: 'center' }}>
                    <SVG name="comment" className="v-sub image-sm"></SVG>
                    <strong className="ml-2">Chat { room && <span className="text-muted">— {room.group.name}</span> }</strong>
                    {statusBadge}
                    <button type="button" className="btn btn-sm btn-link rlz-chat-ctl-btn ml-auto" title="Close this window" onClick={onHide}>
                        <SVG name="close" className="v-sub image-sm"></SVG>
                    </button>
                </div>
                <div style={{ flex: 1, minHeight: 0, display: 'flex' }}>{renderBody('100%')}</div>
            </div>
        );
    }

    return (
        <>
            <Modal show={show && !detached} onHide={onHide} size="lg" backdrop="static">
                <Modal.Header closeButton>
                    <Modal.Title>
                        <SVG name="comment" className="v-sub image-sm"></SVG>
                        <span className="ml-2">Chat { room && <span className="text-muted">— {room.group.name}</span> }</span>
                        {statusBadge}
                        <button type="button" className="btn btn-sm btn-link rlz-chat-ctl-btn ml-3" title="Open in a separate window" onClick={openInWindow}>
                            <SVG name="external-link" className="v-sub image-sm"></SVG>
                        </button>
                        <button type="button" className="btn btn-sm btn-link rlz-chat-ctl-btn" title="Detach from the modal" onClick={() => setDetached(true)}>
                            <SVG name="expand" className="v-sub image-sm"></SVG>
                        </button>
                    </Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    {renderBody(480)}
                </Modal.Body>
            </Modal>

            {show && detached && (
                minimized
                    ?
                    <div
                        onMouseDown={(e) => startDrag(e, 'move', pos, setPos)}
                        style={{
                            position: 'fixed', left: pos.x, top: pos.y, zIndex: 1060,
                            background: '#212529', color: '#fff', borderRadius: 16,
                            padding: '6px 14px', cursor: 'move', userSelect: 'none',
                            display: 'flex', alignItems: 'center',
                            boxShadow: '0 4px 12px rgba(0,0,0,.3)',
                        }}
                    >
                        <SVG name="comment" className="image-sm" style={{ color: '#fff' }}></SVG>
                        <span className="small ml-2">Chat { room && room.group.name }</span>
                        {unread > 0 && (
                            <span style={{ background: '#dc3545', borderRadius: 10, padding: '0 8px', fontSize: 12, marginLeft: 6 }}>
                                {unread}
                            </span>
                        )}
                        <Button size="sm" variant="light" className="ml-2" title="Restore" onClick={restoreFromMinimized}>
                            <SVG name="expand" className="v-sub image-sm"></SVG>
                        </Button>
                        <Button size="sm" variant="light" title="Close" onClick={onHide}>
                            <SVG name="close" className="v-sub image-sm"></SVG>
                        </Button>
                    </div>
                    :
                    <div className="rlz-chat-panel" style={{
                        position: 'fixed', left: pos.x, top: pos.y,
                        width: size.w, height: size.h, zIndex: 1060,
                        borderWidth: 1, borderStyle: 'solid', borderRadius: 8,
                        boxShadow: '0 8px 24px rgba(0,0,0,.25)',
                        display: 'flex', flexDirection: 'column', overflow: 'hidden',
                    }}>
                        <div
                            className="rlz-chat-panel-header"
                            onMouseDown={(e) => startDrag(e, 'move', pos, setPos)}
                            style={{
                                cursor: 'move', userSelect: 'none', padding: '6px 10px',
                                borderBottom: '1px solid',
                                display: 'flex', alignItems: 'center',
                            }}
                        >
                            <SVG name="comment" className="v-sub image-sm"></SVG>
                            <strong className="small ml-2">Chat { room && <span className="text-muted">— {room.group.name}</span> }</strong>
                            {statusBadge}
                            <div className="ml-auto d-flex" onMouseDown={(e) => e.stopPropagation()}>
                                <button type="button" className="btn btn-sm btn-link rlz-chat-ctl-btn" title="Open in a separate window" onClick={openInWindow}>
                                    <SVG name="external-link" className="v-sub image-sm"></SVG>
                                </button>
                                <button type="button" className="btn btn-sm btn-link rlz-chat-ctl-btn" title="Re-attach to the page" onClick={() => setDetached(false)}>
                                    <SVG name="collapse" className="v-sub image-sm"></SVG>
                                </button>
                                <button type="button" className="btn btn-sm btn-link rlz-chat-ctl-btn" title="Minimize" onClick={() => setMinimized(true)}>
                                    <SVG name="dash" className="v-sub image-sm"></SVG>
                                </button>
                                <button type="button" className="btn btn-sm btn-link rlz-chat-ctl-btn" title="Close" onClick={onHide}>
                                    <SVG name="close" className="v-sub image-sm"></SVG>
                                </button>
                            </div>
                        </div>
                        <div style={{ flex: 1, minHeight: 0, display: 'flex' }}>{renderBody('100%')}</div>
                        <div
                            onMouseDown={(e) => startDrag(e, 'resize', size, setSize)}
                            title="Resize"
                            style={{ position: 'absolute', right: 0, bottom: 0, width: 16, height: 16, cursor: 'nwse-resize' }}
                        ></div>
                    </div>
            )}
        </>
    );
}

export default ChatWindow;
