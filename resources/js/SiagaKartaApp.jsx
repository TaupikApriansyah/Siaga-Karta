import React, { useState, useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  AlertCircle, CheckCircle2, ChevronRight, X, Menu, Search,
  Home, Activity, Calendar, FileText, FileSpreadsheet, Users,
  Settings, LogOut, Truck, Plus, Eye, Download, ShieldCheck,
  User, ShieldAlert, Send, MessageCircle, QrCode, Upload, Clock, ReceiptText,
  Ambulance, ArrowRight, HeartHandshake, Bell, MapPin, RefreshCw, Building2
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip as RechartsTooltip, ResponsiveContainer, PieChart, Pie, Cell, BarChart, Bar } from 'recharts';
import api, { errorMessage, getToken, setToken, getAuthMeta, setAuthMeta, newRequestUuid, requestSharedSession, broadcastSession, subscribeAuth } from './api';

const emptyChartData = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'].map(name=>({name,darurat:0,sosial:0}));

const STAFF_ROLES = ['kota','kecamatan','kelurahan'];
const ROLE_LABELS = { kota:'Kota', kecamatan:'Kecamatan', kelurahan:'Kelurahan' };
const roleDisplay = (role) => ROLE_LABELS[role] || role || '-';
const REPORT_CATEGORIES = [
  ['kesehatan','Kesehatan'],['bpjs','BPJS'],['ambulans','Ambulans'],['lansia_disabilitas','Lansia / Disabilitas'],
  ['bantuan_sosial','Bantuan Sosial'],['orang_terlantar','Orang Terlantar'],['anak_keluarga','Anak & Keluarga'],
  ['data_sosial_desil','Data Sosial / Desil'],['kebencanaan','Kebencanaan'],['lainnya','Lainnya'],
];
const REPORT_PRIORITIES = [['darurat','Darurat'],['prioritas','Prioritas'],['reguler','Reguler']];
const WORKFLOW_LABELS = {
  menunggu_kelurahan:'Menunggu verifikasi Kelurahan', diajukan_kecamatan:'Menunggu validasi Kecamatan',
  perlu_perbaikan_kelurahan:'Perlu perbaikan Kelurahan', ditolak_kecamatan:'Ditolak Kecamatan',
  diterima_kota:'Diterima Karang Taruna Kota', diteruskan_opd:'Diteruskan ke OPD / Instansi',
};
const workflowLabel=(value)=>WORKFLOW_LABELS[value] || String(value||'-').replaceAll('_',' ');
const categoryLabel=(value)=>Object.fromEntries(REPORT_CATEGORIES)[value] || value || '-';
const priorityLabel=(value)=>Object.fromEntries(REPORT_PRIORITIES)[value] || value || '-';

const mapReportRow = (r) => ({
  id:r.code, nama:r.citizen?.name||'-', lokasi:r.pickup_location, kondisi:r.medical_condition||r.description, jenis:r.type, kategori:r.category,
  prioritas:r.priority, status:r.status, workflow:r.workflow_status, eskalasi:r.escalation_level, kelurahan:r.region?.name||'-', kecamatan:r.region?.parent?.name||'-',
  tgl:r.created_at, sumber:r.source, scheduled_at:r.scheduled_at, service_start_at:r.service_start_at,
  service_end_at:r.service_end_at, ambulance:r.ambulance?.code||null, driver:r.driver?.name||null,
});
const mapTransactionRow = (t) => ({
  db_id:t.id,id:t.code,tipe:t.type,kategori:t.category,nominal:t.amount,status:t.status,
  tgl:typeof t.transaction_date==='string'?t.transaction_date.slice(0,10):t.transaction_date,source:t.source,
  payer_name:t.payer_name,payer_phone_last4:t.payer_phone_last4,has_proof:Boolean(t.has_proof??t.payment_proof_path),
  rejection_reason:t.rejection_reason,description:t.description,
});

const statusLabel = (value) => ({
  pending:'Menunggu Verifikasi', verified:'Terverifikasi', rejected:'Ditolak',
  menunggu:'Menunggu', diproses:'Sedang Diproses', dijemput:'Dalam Penjemputan', selesai:'Selesai', ditolak:'Ditolak',
  tersedia:'Tersedia', dipesan:'Dipesan', bertugas:'Sedang Bertugas', maintenance:'Pemeliharaan',
}[value] || String(value || '-').replaceAll('_',' '));
const serviceTypeLabel = (value) => ({darurat:'Darurat',terjadwal:'Terjadwal'}[value] || value || '-');

const Button = ({ children, variant = 'primary', size = 'md', className = '', ...props }) => {
  const base = "inline-flex items-center justify-center font-bold rounded-xl transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100";
  const sizes = { sm: "px-3 py-1.5 text-xs", md: "px-4 py-2.5 text-sm", lg: "px-6 py-3.5 text-base" };
  const variants = {
    primary: "bg-[#0b3b78] text-white hover:bg-[#092f61] shadow-md shadow-blue-950/10",
    danger: "bg-red-600 text-white hover:bg-red-700 shadow-md",
    success: "bg-emerald-600 text-white hover:bg-emerald-700 shadow-md",
    outline: "border-2 border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50",
    ghost: "text-slate-600 hover:bg-slate-100"
  };
  return <button className={`${base} ${sizes[size]} ${variants[variant]} ${className}`} {...props}>{children}</button>;
};

const Card = ({ children, className = '', noPadding = false }) => {
  const hasCustomBackground = /(^|\s)bg-/.test(className);
  return <div className={`${hasCustomBackground ? '' : 'bg-white'} rounded-3xl border border-slate-100 shadow-sm overflow-hidden ${noPadding ? '' : 'p-6'} ${className}`}>{children}</div>;
};

const BrandLogo = ({ className = 'h-10 w-10', light = false }) => (
  <div className={`relative shrink-0 overflow-hidden rounded-full ${light ? 'bg-white' : 'bg-white'} ${className}`}>
    <span className="absolute inset-0 flex items-center justify-center text-[10px] font-black text-[#0b3b78]">KT</span>
    <img src={KARANG_TARUNA_LOGO} alt="Logo Karang Taruna" className="relative z-10 h-full w-full object-contain p-1" referrerPolicy="no-referrer" onError={(event)=>{event.currentTarget.style.display='none';}}/>
  </div>
);

const DeveloperWatermark = () => (
  <a href="https://github.com/TaupikApriansyah" target="_blank" rel="noreferrer" className="fixed bottom-2 left-1/2 z-[40] -translate-x-1/2 rounded-full border border-slate-300/60 bg-white/80 px-3 py-1 text-[9px] font-semibold tracking-wide text-slate-600 shadow-sm backdrop-blur-md transition hover:bg-white hover:text-slate-900 sm:left-auto sm:right-3 sm:translate-x-0">
    Developer · Taupik Apriansyah · STMIK MARDIRA INDONESIA
  </a>
);

const FieldGuide = ({ label, help, required = false, children, className = '' }) => (
  <div className={`space-y-1.5 ${className}`}>
    <label className="block text-sm font-bold text-slate-800">{label}{required && <span className="text-red-600"> *</span>}</label>
    {help && <p className="text-xs leading-5 text-slate-500">{help}</p>}
    {children}
  </div>
);

const ProtectedNikInput = ({ className = '', ...props }) => {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const hideNik = () => setVisible(false);
    const onVisibility = () => { if (document.hidden) hideNik(); };
    const onKey = (event) => {
      if (event.key === 'PrintScreen') hideNik();
    };

    window.addEventListener('blur', hideNik);
    window.addEventListener('keydown', onKey, true);
    window.addEventListener('keyup', onKey, true);
    document.addEventListener('visibilitychange', onVisibility);
    return () => {
      window.removeEventListener('blur', hideNik);
      window.removeEventListener('keydown', onKey, true);
      window.removeEventListener('keyup', onKey, true);
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, []);

  const blockCopy = (event) => {
    event.preventDefault();
    if (event.clipboardData) event.clipboardData.setData('text/plain', '');
  };

  const blockCopyShortcut = (event) => {
    if ((event.ctrlKey || event.metaKey) && ['c', 'x'].includes(event.key.toLowerCase())) {
      event.preventDefault();
    }
  };

  return (
    <div className="relative">
      <input
        {...props}
        type={visible ? 'text' : 'password'}
        autoComplete="off"
        onCopy={blockCopy}
        onCut={blockCopy}
        onContextMenu={(event) => event.preventDefault()}
        onDragStart={(event) => event.preventDefault()}
        onKeyDown={blockCopyShortcut}
        className={`${className} pr-28`}
        data-sensitive="nik"
        aria-label={props['aria-label'] || 'NIK 16 digit'}
      />
      <button
        type="button"
        onClick={() => setVisible((current) => !current)}
        className="absolute inset-y-0 right-0 px-4 text-xs font-bold text-slate-600 hover:text-slate-900"
        aria-pressed={visible}
        aria-label={visible ? 'Sembunyikan NIK' : 'Tampilkan NIK'}
      >
        {visible ? 'Sembunyikan' : 'Tampilkan'}
      </button>
    </div>
  );
};

const ProtectedNikText = ({ children, className = '' }) => (
  <span
    className={className}
    onCopy={(event) => event.preventDefault()}
    onCut={(event) => event.preventDefault()}
    onContextMenu={(event) => event.preventDefault()}
    onDragStart={(event) => event.preventDefault()}
    onKeyDown={(event) => {
      if ((event.ctrlKey || event.metaKey) && ['c', 'x'].includes(event.key.toLowerCase())) event.preventDefault();
    }}
    style={{ userSelect: 'none', WebkitUserSelect: 'none' }}
    data-sensitive="nik"
  >
    {children}
  </span>
);

class DashboardErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, message: '' };
  }
  static getDerivedStateFromError(error) {
    return { hasError: true, message: error?.message || 'Komponen dashboard gagal dimuat.' };
  }
  componentDidCatch(error) {
    console.error('Dashboard render error:', error);
  }
  componentDidUpdate(prevProps) {
    if (this.state.hasError && prevProps.resetKey !== this.props.resetKey) {
      this.setState({ hasError: false, message: '' });
    }
  }
  render() {
    if (!this.state.hasError) return this.props.children;
    return (
      <Card className="border-red-200 bg-red-50">
        <div className="flex items-start gap-4">
          <div className="rounded-2xl bg-red-100 p-3 text-red-700"><AlertCircle className="h-6 w-6"/></div>
          <div className="min-w-0">
            <h3 className="font-black text-red-950">Halaman gagal ditampilkan</h3>
            <p className="mt-1 text-sm leading-6 text-red-800">{this.state.message}</p>
            <p className="mt-2 text-xs text-red-700">Pilih menu lain lalu kembali lagi. Dashboard tidak lagi berubah menjadi halaman putih saat satu komponen mengalami error.</p>
          </div>
        </div>
      </Card>
    );
  }
}

const ConfirmModal = ({ isOpen, title, msg, confirmText, cancelText, onConfirm, onCancel, type = 'danger' }) => {
  if (!isOpen) return null;
  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-200">
        <div className="p-6">
          <div className={`w-12 h-12 rounded-full flex items-center justify-center mb-4
            ${type === 'danger' ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600'}`}>
            {type === 'danger' ? <AlertCircle className="w-6 h-6" /> : <CheckCircle2 className="w-6 h-6" />}
          </div>
          <h3 className="text-lg font-bold text-slate-900 mb-2">{title}</h3>
          <p className="text-sm text-slate-600">{msg}</p>
        </div>
        <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3 justify-end">
          <Button variant="ghost" onClick={onCancel}>{cancelText || 'Batal'}</Button>
          <Button variant={type === 'danger' ? 'danger' : 'success'} onClick={onConfirm}>{confirmText || 'Konfirmasi'}</Button>
        </div>
      </div>
    </div>
  );
};

const ModalForm = ({ isOpen, onClose, title, children }) => {
  if (!isOpen) return null;
  return (
    <div className="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200">
      <div className="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
        <div className="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white sticky top-0 z-10">
          <h3 className="text-xl font-black text-slate-900 tracking-tight">{title}</h3>
          <button type="button" onClick={onClose} className="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-full transition-colors"><X className="w-5 h-5"/></button>
        </div>
        <div className="p-6 overflow-y-auto flex-1 hide-scroll bg-slate-50/50 text-slate-900">
          {children}
        </div>
      </div>
    </div>
  );
};

const SiagaBot = ({ db }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [msgs, setMsgs] = useState([{ sender: 'bot', text: 'Halo warga! Saya SiagaBot. Ada yang bisa dibantu? Ketik "cek ambulans" untuk info unit saat ini.' }]);
  const [input, setInput] = useState('');
  const messagesEndRef = useRef(null);

  useEffect(() => {
    if (messagesEndRef.current) messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
  }, [msgs, isOpen]);

  const handleSend = (e) => {
    e.preventDefault();
    if (!input.trim()) return;

    setMsgs(prev => [...prev, { sender: 'user', text: input }]);
    const query = input.toLowerCase();
    setInput('');

    api.post('/public/bot', { message: query })
      .then(({ data }) => setMsgs(prev => [...prev, { sender: 'bot', text: data.reply }]))
      .catch(() => setMsgs(prev => [...prev, { sender: 'bot', text: 'Layanan bot sedang tidak tersedia. Silakan gunakan menu layanan utama.' }]));
  };

  return (
    <>
      <button
        onClick={() => setIsOpen(true)}
        className={`fixed bottom-6 right-6 w-14 h-14 bg-[#07132f] text-white rounded-full shadow-2xl flex items-center justify-center hover:bg-[#10295b] hover:scale-110 transition-all z-50 ${isOpen ? 'scale-0 opacity-0' : 'scale-100 opacity-100'}`}
      >
        <MessageCircle className="w-6 h-6" />
      </button>

      {isOpen && (
        <div className="fixed bottom-4 right-4 left-4 sm:left-auto sm:bottom-6 sm:right-6 w-auto sm:w-96 bg-white rounded-3xl shadow-2xl border border-slate-100 z-50 overflow-hidden flex flex-col h-[500px] animate-in slide-in-from-bottom-8">
          <div className="bg-[#07132f] p-4 flex justify-between items-center text-white">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center font-bold">SB</div>
              <div>
                <div className="font-bold">SiagaBot</div>
                <div className="text-xs text-emerald-100 flex items-center gap-1">
                  <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Aktif
                </div>
              </div>
            </div>
            <button onClick={() => setIsOpen(false)} className="p-2 hover:bg-white/10 rounded-full transition-colors"><X className="w-5 h-5"/></button>
          </div>
          <div className="flex-1 p-4 bg-slate-50 overflow-y-auto space-y-4">
            {msgs.map((m, i) => (
              <div key={i} className={`flex ${m.sender === 'user' ? 'justify-end' : 'justify-start'}`}>
                <div className={`px-4 py-2.5 rounded-2xl max-w-[85%] text-sm ${m.sender === 'user' ? 'bg-[#111] text-white rounded-br-sm' : 'bg-white border border-slate-200 text-slate-700 rounded-bl-sm shadow-sm'}`}>
                  {m.text}
                </div>
              </div>
            ))}
            <div ref={messagesEndRef} />
          </div>
          <form onSubmit={handleSend} className="p-3 bg-white border-t border-slate-100 flex gap-2">
            <input
              value={input} onChange={(e)=>setInput(e.target.value)}
              placeholder="Tanya ketersediaan ambulans..."
              className="flex-1 px-4 py-2 bg-slate-100 rounded-xl outline-none focus:ring-2 focus:ring-[#07132f] text-sm font-medium text-slate-950 caret-slate-950 placeholder:text-slate-500"
            />
            <button type="submit" className="w-10 h-10 bg-[#07132f] text-white rounded-xl flex items-center justify-center hover:bg-[#10295b] transition-colors">
              <Send className="w-4 h-4" />
            </button>
          </form>
        </div>
      )}
    </>
  );
};

const LANDING_HERO_IMAGE = '/hero-ambulance.png';
const LANDING_COMMUNITY_IMAGE = '/siaga-karta-community.png';
const KARANG_TARUNA_LOGO = 'https://upload.wikimedia.org/wikipedia/commons/b/b0/Logo_Karang_Taruna.png';

const useLandingScroll = () => {
  const [isScrolled, setIsScrolled] = useState(false);
  useEffect(() => {
    const onScroll = () => setIsScrolled(window.scrollY > 40);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);
  return isScrolled;
};

const PublicView = ({ setRole, db, publicStats, addToast, refreshPublic }) => {
  const isScrolled = useLandingScroll();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [formType, setFormType] = useState('darurat');
  const [reportCategory, setReportCategory] = useState('ambulans');
  const [reportPriority, setReportPriority] = useState('reguler');
  const [publicRegions, setPublicRegions] = useState([]);
  const [publicRegionsLoading, setPublicRegionsLoading] = useState(false);
  const [reportCoords, setReportCoords] = useState({latitude:'',longitude:''});
  const [showReport, setShowReport] = useState(false);
  const [showTrack, setShowTrack] = useState(false);
  const [showInfaq, setShowInfaq] = useState(false);
  const [showSuccess, setShowSuccess] = useState(false);
  const [trackCode, setTrackCode] = useState('');
  const [trackResult, setTrackResult] = useState(null);
  const [trackError, setTrackError] = useState('');
  const [trackLoading, setTrackLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [infaqInfo, setInfaqInfo] = useState({ active:false, title:'Infaq Siaga Karta', description:'', payment_instructions:'', has_qr:false, qr_url:null, bank_name:null, account_number:null, account_name:null });
  const [infaqSubmitting, setInfaqSubmitting] = useState(false);
  const [infaqCode, setInfaqCode] = useState('');
  const [reportError, setReportError] = useState('');
  const [reportDirty, setReportDirty] = useState(false);
  const [infaqError, setInfaqError] = useState('');
  const reportRequestUuidRef = useRef(newRequestUuid());
  const infaqRequestUuidRef = useRef(newRequestUuid());

  const loadInfaqInfo = async () => {
    try { const {data}=await api.get('/public/infaq'); setInfaqInfo(data.infaq || {}); } catch {}
  };
  useEffect(() => { loadInfaqInfo(); }, []);
  const loadPublicRegions=async()=>{if(publicRegions.length||publicRegionsLoading)return;setPublicRegionsLoading(true);try{const {data}=await api.get('/public/regions');setPublicRegions(data.kelurahan||[]);}catch{setPublicRegions([]);}finally{setPublicRegionsLoading(false);}};
  const openInfaq = async () => { setInfaqCode(''); setInfaqError(''); await loadInfaqInfo(); setShowInfaq(true); };

  useEffect(() => {
    const warnUnsaved = (event) => {
      if (!reportDirty) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', warnUnsaved);
    return () => window.removeEventListener('beforeunload', warnUnsaved);
  }, [reportDirty]);

  const closeReport = () => {
    if (reportDirty && !window.confirm('Data laporan belum dikirim. Tutup dan buang perubahan?')) return;
    setReportDirty(false);
    setShowReport(false);
    setReportError('');
  };

  const openReport = (type) => {
    setReportCategory('ambulans');
    setReportPriority(type==='darurat'?'darurat':'reguler');
    setReportCoords({latitude:'',longitude:''});
    setFormType(type);
    reportRequestUuidRef.current = newRequestUuid();
    setReportError('');
    setReportDirty(false);
    loadPublicRegions();
    setShowReport(true);
    setMobileMenuOpen(false);
  };

  const normalizeTrackingCode = (value) => {
    const upper = String(value || '').toUpperCase();
    const match = upper.match(/(?:SKB-[A-Z0-9]+-\d{4}-\d{5}|(?:LPR|BPJ|BNC|JDL)-\d{8}-[A-Z0-9]{10})/);
    return (match?.[0] || upper).replace(/\s+/g, '').slice(0, 64);
  };

  const openTrack = () => {
    setTrackResult(null);
    setTrackError('');
    setTrackCode((value) => normalizeTrackingCode(value));
    setShowTrack(true);
    setMobileMenuOpen(false);
  };

  const handleLaporSubmit = async (e) => {
    e.preventDefault();
    const formElement = e.currentTarget;
    setReportError('');
    if (!formElement.checkValidity()) {
      formElement.reportValidity();
      return;
    }
    setSubmitting(true);
    try {
      const form = new FormData(formElement);
      form.set('request_uuid', reportRequestUuidRef.current);
      form.set('category', reportCategory);
      if(reportCategory === 'ambulans') form.set('type', formType); else form.delete('type');
      form.set('priority', reportPriority);
      if(reportCoords.latitude && reportCoords.longitude){ form.set('latitude',reportCoords.latitude); form.set('longitude',reportCoords.longitude); }
      const { data } = await api.post('/public/reports', form);
      setTrackCode(data.tracking_code);
      setTrackResult(null);
      setReportDirty(false);
      setShowReport(false);
      setShowSuccess(true);
      formElement.reset();
      reportRequestUuidRef.current = newRequestUuid();
      addToast(data.message, 'success');
      refreshPublic?.();
    } catch (err) {
      const msg = errorMessage(err);
      setReportError(msg);
      addToast(msg, 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleTrack = async () => {
    const code = normalizeTrackingCode(trackCode);
    setTrackCode(code);
    setTrackResult(null);
    setTrackError('');
    if (!code) {
      setTrackError('Masukkan kode laporan terlebih dahulu.');
      return;
    }
    if (!/^(?:SKB-[A-Z0-9]+-\d{4}-\d{5}|(?:LPR|BPJ|BNC|JDL)-\d{8}-[A-Z0-9]{10})$/.test(code)) {
      setTrackError('Format kode belum sesuai. Gunakan kode lengkap yang diterima setelah laporan dikirim.');
      return;
    }
    setTrackLoading(true);
    try {
      const { data } = await api.get(`/public/reports/${encodeURIComponent(code)}`);
      setTrackResult(data.report);
      addToast('Status laporan ditemukan.', 'success');
    } catch (err) {
      const msg = err?.response?.status === 404 ? 'Kode laporan tidak ditemukan. Periksa kembali kode yang Anda masukkan.' : errorMessage(err);
      setTrackError(msg);
      addToast(msg, 'error');
    } finally {
      setTrackLoading(false);
    }
  };

  useEffect(() => {
    if (!showTrack || !trackResult?.code) return;
    let cancelled = false;
    const refreshTrackedReport = async () => {
      try {
        const { data } = await api.get(`/public/reports/${encodeURIComponent(trackResult.code)}`);
        if (!cancelled) setTrackResult(data.report);
      } catch {}
    };
    const timer = setInterval(refreshTrackedReport, 5000);
    return () => { cancelled = true; clearInterval(timer); };
  }, [showTrack, trackResult?.code]);

  const handleInfaqSubmit = async (e) => {
    e.preventDefault();
    const formElement = e.currentTarget;
    setInfaqError('');
    if (!formElement.checkValidity()) {
      formElement.reportValidity();
      return;
    }
    setInfaqSubmitting(true);
    try {
      const form = new FormData(formElement);
      form.set('request_uuid', infaqRequestUuidRef.current);
      const { data } = await api.post('/public/infaq/payments', form);
      setInfaqCode(data.payment_code);
      addToast(data.message, 'success');
      formElement.reset();
      infaqRequestUuidRef.current = newRequestUuid();
      refreshPublic?.();
    } catch (err) {
      const msg = errorMessage(err);
      setInfaqError(msg);
      addToast(msg, 'error');
    } finally {
      setInfaqSubmitting(false);
    }
  };

  const menu = [
    { label:'Beranda', href:'#beranda' },
    { label:'Layanan', href:'#layanan' },
    { label:'Program Sosial', href:'#program-sosial' },
    { label:'Profil Karang Taruna', href:'#karang-taruna' },
    { label:'Tentang Sistem', href:'#tentang' },
  ];

  const serviceCards = [
    { icon: Ambulance, title:'Layanan Warga', desc:'Ajukan pengaduan dalam 10 kategori layanan. Semua laporan masuk lebih dulu ke Karang Taruna Kelurahan untuk verifikasi awal.', action:() => openReport('darurat'), actionLabel:'Buat Laporan', tone:'text-red-300 bg-red-400/10 border-red-300/20' },
    { icon: Calendar, title:'Jadwalkan Ambulans', desc:'Ajukan penjemputan terjadwal. Sistem memeriksa ketersediaan ambulans dan pengemudi agar jadwal pelayanan tidak saling berbenturan.', action:() => openReport('terjadwal'), actionLabel:'Pilih Jadwal', tone:'text-cyan-300 bg-cyan-300/10 border-cyan-300/20' },
    { icon: Search, title:'Periksa Status Layanan', desc:'Masukkan kode laporan untuk melihat perkembangan layanan, unit ambulans dan pengemudi yang telah ditugaskan.', action:openTrack, actionLabel:'Periksa Status', tone:'text-blue-300 bg-blue-300/10 border-blue-300/20' },
    { icon: HeartHandshake, title:'Infaq Warga', desc:'Bayar melalui QR atau rekening resmi Siaga Karta, lalu unggah bukti pembayaran untuk diverifikasi.', action:openInfaq, actionLabel:'Dukung Program', tone:'text-emerald-300 bg-emerald-300/10 border-emerald-300/20' },
  ];

  return (
    <div className="min-h-[100dvh] bg-[#050b1c] font-sans text-white selection:bg-cyan-300/30 selection:text-white">
      <nav className={`fixed inset-x-0 top-0 z-50 transition-all duration-300 ${isScrolled ? 'border-b border-white/10 bg-[#061027]/90 shadow-2xl shadow-black/20 backdrop-blur-xl' : 'bg-transparent'}`}>
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-7 lg:px-8">
          <a href="#beranda" className="flex items-center gap-3" onClick={() => setMobileMenuOpen(false)}>
            <BrandLogo className="h-11 w-11" light/>
            <div>
              <div className="text-base font-black tracking-[0.08em] text-white sm:text-lg">SIAGA KARTA</div>
              <div className="hidden text-[10px] font-semibold uppercase tracking-[0.22em] text-cyan-300/80 sm:block">Layanan Warga Terpadu</div>
            </div>
          </a>

          <div className="hidden items-center gap-7 lg:flex">
            {menu.map((item) => <a key={item.label} href={item.href} className="group relative text-sm font-semibold text-slate-200/85 transition-colors hover:text-white">{item.label}<span className="absolute -bottom-2 left-0 h-px w-0 bg-cyan-300 transition-all duration-300 group-hover:w-full"/></a>)}
          </div>

          <div className="hidden items-center gap-3 md:flex">
            <button onClick={openTrack} className="rounded-full border border-white/30 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-cyan-300 hover:bg-cyan-300/10">Periksa Status</button>
            <button onClick={() => setRole('login')} className="rounded-full bg-white px-5 py-2.5 text-sm font-bold text-[#07132f] transition hover:bg-cyan-50">Portal Administrasi</button>
          </div>

          <button type="button" aria-label="Buka menu" className="rounded-xl border border-white/10 p-2 text-white md:hidden" onClick={() => setMobileMenuOpen(v => !v)}>{mobileMenuOpen ? <X className="h-6 w-6"/> : <Menu className="h-6 w-6"/>}</button>
        </div>

        <AnimatePresence>
          {mobileMenuOpen && <motion.div initial={{ opacity:0, height:0 }} animate={{ opacity:1, height:'auto' }} exit={{ opacity:0, height:0 }} className="overflow-hidden border-t border-white/10 bg-[#061027]/95 backdrop-blur-xl md:hidden">
            <div className="space-y-1 px-5 py-5">
              {menu.map((item) => <a key={item.label} href={item.href} onClick={() => setMobileMenuOpen(false)} className="block rounded-xl px-3 py-3 text-sm font-semibold text-slate-200 hover:bg-white/5 hover:text-white">{item.label}</a>)}
              <button onClick={openTrack} className="block w-full rounded-xl px-3 py-3 text-left text-sm font-semibold text-cyan-200 hover:bg-white/5">Periksa Status Laporan</button>
              <button onClick={() => { setMobileMenuOpen(false); setRole('login'); }} className="mt-2 w-full rounded-xl bg-white px-4 py-3 text-sm font-bold text-[#07132f]">Portal Administrasi</button>
            </div>
          </motion.div>}
        </AnimatePresence>
      </nav>

      <main>
        <section id="beranda" className="relative isolate flex min-h-[780px] items-center overflow-hidden bg-[#081531] pt-24 md:min-h-screen">
          <div className="absolute inset-0 bg-cover bg-[64%_center] bg-no-repeat md:bg-center" style={{ backgroundImage:`url("${LANDING_HERO_IMAGE}")` }}/>
          <div className="absolute inset-0 bg-gradient-to-r from-[#07132f]/98 via-[#0a1a3d]/80 to-[#07132f]/12"/>
          <div className="absolute inset-0 bg-gradient-to-t from-[#050b1c] via-transparent to-[#07132f]/30"/>
          <div className="absolute left-[8%] top-[20%] h-72 w-72 rounded-full bg-indigo-500/20 blur-[110px]"/>
          <div className="relative z-10 mx-auto w-full max-w-7xl px-5 pb-24 pt-20 sm:px-7 lg:px-8">
            <div className="max-w-2xl">
              <motion.div initial={{ opacity:0, y:16 }} animate={{ opacity:1, y:0 }} transition={{ duration:.65 }} className="mb-7 inline-flex items-center gap-2 rounded-full border border-cyan-300/25 bg-cyan-300/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-cyan-200 backdrop-blur">
                <Activity className="h-4 w-4"/> Layanan Ambulans & Sosial Warga
              </motion.div>
              <motion.h1 initial={{ opacity:0, y:20 }} animate={{ opacity:1, y:0 }} transition={{ duration:.7, delay:.08 }} className="max-w-2xl text-4xl font-light leading-[1.06] tracking-tight text-white sm:text-5xl md:text-6xl lg:text-[70px]">
                Cepat saat darurat.
                <span className="mt-2 block font-semibold text-cyan-100">Teratur saat dijadwalkan.</span>
              </motion.h1>
              <motion.p initial={{ opacity:0, y:18 }} animate={{ opacity:1, y:0 }} transition={{ duration:.65, delay:.2 }} className="mt-7 max-w-xl text-base font-light leading-8 text-slate-200/90 sm:text-lg">
                SIAGA KARTA menghubungkan warga dengan Karang Taruna Kelurahan, Kecamatan, dan Kota melalui alur pengaduan yang dapat dilacak, termasuk layanan kesehatan, BPJS, ambulans, sosial, keluarga, kebencanaan, dan kategori lainnya.
              </motion.p>
              <motion.div initial={{ opacity:0, y:18 }} animate={{ opacity:1, y:0 }} transition={{ duration:.65, delay:.3 }} className="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <button onClick={() => openReport('darurat')} className="inline-flex items-center justify-center gap-2 rounded-full bg-red-600 px-7 py-3.5 text-sm font-bold text-white shadow-xl shadow-red-950/25 transition hover:-translate-y-0.5 hover:bg-red-500"><AlertCircle className="h-4 w-4"/> Butuh Ambulans Darurat</button>
                <button onClick={() => openReport('terjadwal')} className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-7 py-3.5 text-sm font-bold text-[#07132f] transition hover:-translate-y-0.5 hover:bg-cyan-50"><Calendar className="h-4 w-4"/> Jadwalkan Ambulans</button>
                <button onClick={openTrack} className="inline-flex items-center justify-center gap-2 rounded-full border border-white/40 bg-white/5 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition hover:border-cyan-200/70 hover:bg-cyan-200/10"><Search className="h-4 w-4"/> Periksa Status</button>
              </motion.div>
            </div>
          </div>
          <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-b from-transparent to-[#050b1c]"/>
        </section>

        <section className="relative z-10 -mt-10 px-5 sm:px-7 lg:px-8">
          <div className="mx-auto grid max-w-7xl grid-cols-2 gap-3 rounded-3xl border border-white/10 bg-[#08142d]/90 p-3 shadow-2xl shadow-black/30 backdrop-blur-xl md:grid-cols-4 md:gap-0 md:p-2">
            {[
              [publicStats.ambulans_tersedia ?? 0, 'Ambulans Tersedia'],
              [publicStats.layanan_selesai ?? 0, 'Layanan Selesai'],
              [publicStats.program_aktif ?? 0, 'Program Aktif'],
              [`Rp${Number(publicStats.bantuan_disalurkan || 0).toLocaleString('id-ID')}`, 'Bantuan Disalurkan'],
            ].map(([value,label],i) => <div key={label} className={`rounded-2xl px-3 py-5 text-center ${i<3?'md:border-r md:border-white/10':''}`}><div className="text-xl font-black text-white sm:text-2xl">{value}</div><div className="mt-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400 sm:text-xs">{label}</div></div>)}
          </div>
        </section>

        <section id="layanan" className="relative overflow-hidden bg-[#050b1c] py-28">
          <div className="absolute left-1/2 top-0 h-96 w-[70%] -translate-x-1/2 rounded-full bg-blue-700/10 blur-[120px]"/>
          <div className="relative mx-auto max-w-7xl px-5 sm:px-7 lg:px-8">
            <motion.div initial={{ opacity:0, y:28 }} whileInView={{ opacity:1, y:0 }} viewport={{ once:true, margin:'-100px' }} transition={{ duration:.65 }} className="max-w-2xl">
              <p className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">Layanan Terintegrasi</p>
              <h2 className="mt-4 text-3xl font-light tracking-tight text-white md:text-5xl">Satu akses untuk kebutuhan warga.</h2>
              <p className="mt-5 text-sm leading-7 text-slate-400 sm:text-base">Seluruh layanan di bawah terhubung langsung ke sistem SIAGA KARTA dan menggunakan alur data yang sama.</p>
            </motion.div>
            <div className="mt-14 grid border-y border-white/10 md:grid-cols-2">
              {serviceCards.map((item,index) => { const Icon=item.icon; return <motion.button key={item.title} type="button" onClick={item.action} initial={{ opacity:0, y:18 }} whileInView={{ opacity:1, y:0 }} viewport={{ once:true, margin:'-70px' }} transition={{ duration:.45, delay:index*.06 }} className={`group flex gap-5 border-white/10 py-7 text-left transition hover:bg-white/[0.025] md:px-6 ${index%2===0?'md:border-r':''} ${index<2?'border-b':''}`}>
                <div className={`mt-1 flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border ${item.tone}`}><Icon className="h-5 w-5"/></div>
                <div><h3 className="text-lg font-bold text-white">{item.title}</h3><p className="mt-2 text-sm font-light leading-7 text-slate-400">{item.desc}</p><span className="mt-3 inline-flex items-center gap-2 text-sm font-bold text-cyan-300">{item.actionLabel}<ArrowRight className="h-4 w-4 transition group-hover:translate-x-1"/></span></div>
              </motion.button>; })}
            </div>
          </div>
        </section>

        <section id="alur-layanan" className="relative overflow-hidden bg-[#040917] py-28">
          <div className="absolute inset-x-0 top-1/2 h-80 -translate-y-1/2 bg-cyan-700/5 blur-[120px]"/>
          <div className="relative mx-auto max-w-7xl px-5 sm:px-7 lg:px-8">
            <motion.div initial={{ opacity:0, y:28 }} whileInView={{ opacity:1, y:0 }} viewport={{ once:true, margin:'-90px' }} transition={{ duration:.65 }} className="mx-auto max-w-3xl text-center">
              <p className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">Alur Layanan Warga</p>
              <h2 className="mt-4 text-3xl font-light tracking-tight text-white md:text-5xl">Dari laporan sampai selesai, alurnya terlihat.</h2>
              <p className="mt-5 text-sm leading-7 text-slate-400 sm:text-base">Scroll ke bawah untuk mengikuti perjalanan laporan warga. Setiap tahap memiliki status yang bisa dipantau.</p>
            </motion.div>
            <div className="relative mx-auto mt-16 max-w-5xl">
              <div className="absolute bottom-10 left-7 top-10 w-px bg-gradient-to-b from-cyan-300/60 via-blue-400/30 to-emerald-300/60 md:left-1/2"/>
              {[
                { n:'01', title:'Warga mengirim laporan', text:'Laporan dapat masuk melalui WhatsApp, RT/RW, datang langsung, telepon, atau Form Online. Gmail warga dicatat untuk pengiriman kode pelacakan.', icon:Send },
                { n:'02', title:'Kelurahan melakukan verifikasi awal', text:'Karang Taruna Kelurahan menerima pengaduan, mengecek kelengkapan, lalu mengajukannya ke Kecamatan untuk validasi dan cross-check.', icon:Activity },
                { n:'03', title:'Layanan diproses', text:'Untuk ambulans, sistem memeriksa konflik jadwal unit dan pengemudi. Untuk pengaduan sosial, petugas memperbarui status penanganan.', icon:Ambulance },
                { n:'04', title:'Warga memantau hasil', text:'Kode laporan digunakan untuk melihat progres sampai layanan selesai. Status berubah tanpa warga harus menanyakan ulang ke petugas.', icon:CheckCircle2 },
              ].map((step,index) => { const Icon=step.icon; const left=index%2===0; return (
                <motion.div key={step.n} initial={{ opacity:0, y:40 }} whileInView={{ opacity:1, y:0 }} viewport={{ once:true, margin:'-80px' }} transition={{ duration:.6, delay:index*.08 }} className={`relative mb-10 flex md:mb-14 ${left?'md:justify-start':'md:justify-end'}`}>
                  <div className={`ml-16 w-[calc(100%-4rem)] rounded-3xl border border-white/10 bg-white/[0.045] p-6 shadow-xl shadow-black/10 md:ml-0 md:w-[45%] ${left?'md:mr-auto':'md:ml-auto'}`}>
                    <div className="flex items-center gap-3"><span className="font-mono text-xs font-black tracking-widest text-cyan-300">{step.n}</span><div className="h-px flex-1 bg-white/10"/><Icon className="h-5 w-5 text-cyan-200"/></div>
                    <h3 className="mt-5 text-xl font-bold text-white">{step.title}</h3>
                    <p className="mt-3 text-sm leading-7 text-slate-400">{step.text}</p>
                  </div>
                  <motion.div whileInView={{ scale:[.75,1.12,1] }} viewport={{ once:true }} transition={{ duration:.55, delay:index*.08 }} className="absolute left-2 top-7 flex h-11 w-11 items-center justify-center rounded-full border border-cyan-300/40 bg-[#07132f] text-xs font-black text-cyan-200 shadow-[0_0_30px_rgba(103,232,249,0.12)] md:left-1/2 md:-translate-x-1/2">{step.n}</motion.div>
                </motion.div>
              ); })}
            </div>
          </div>
        </section>

        <section id="program-sosial" className="bg-[#071128] py-24">
          <div className="mx-auto max-w-7xl px-5 sm:px-7 lg:px-8">
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
              <div className="max-w-2xl"><p className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">Program Bantuan</p><h2 className="mt-4 text-3xl font-light tracking-tight text-white md:text-5xl">Transparansi program sosial.</h2><p className="mt-5 text-sm leading-7 text-slate-400 sm:text-base">Warga dapat melihat target, dana terkumpul, dan bantuan yang telah disalurkan.</p></div>
              <button onClick={() => { setInfaqCode(''); setShowInfaq(true); }} className="inline-flex items-center justify-center gap-2 self-start rounded-full border border-cyan-300/30 bg-cyan-300/10 px-6 py-3 text-sm font-bold text-cyan-200 hover:bg-cyan-300/15"><QrCode className="h-4 w-4"/> Dukung Program</button>
            </div>
            <div className="mt-12 grid gap-6 md:grid-cols-2">
              {db.program.length ? db.program.map((p,index) => {
                const pct = p.target > 0 ? Math.min(100, (p.terkumpul/p.target)*100) : 0;
                return <motion.article key={p.id} initial={{ opacity:0, y:24 }} whileInView={{ opacity:1, y:0 }} viewport={{ once:true }} transition={{ duration:.55, delay:index*.08 }} className="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.045]">
                  {p.img && <div className="h-56 overflow-hidden bg-slate-900"><img src={p.img} alt={p.nama} className="h-full w-full object-cover transition duration-500 hover:scale-105"/></div>}
                  <div className="p-6"><div className="flex items-start justify-between gap-4"><h3 className="text-xl font-bold text-white">{p.nama}</h3><span className="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300">AKTIF</span></div>
                    <div className="mt-6"><div className="flex justify-between gap-3 text-xs font-semibold text-slate-400"><span>Terkumpul Rp{Number(p.terkumpul).toLocaleString('id-ID')}</span><span className="text-cyan-300">{pct.toFixed(0)}%</span></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-white/10"><div className="h-full rounded-full bg-cyan-300" style={{ width:`${pct}%` }}/></div></div>
                    <div className="mt-5 flex items-center justify-between border-t border-white/10 pt-5"><div><div className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Telah Disalurkan</div><div className="mt-1 font-bold text-white">Rp{Number(p.tersalurkan).toLocaleString('id-ID')}</div></div><button onClick={() => { setInfaqCode(''); setShowInfaq(true); }} className="rounded-full border border-white/15 px-4 py-2 text-xs font-bold text-white hover:border-cyan-300/40 hover:text-cyan-200">Dukung Program</button></div>
                  </div>
                </motion.article>;
              }) : <div className="md:col-span-2 rounded-3xl border border-white/10 bg-white/[0.04] p-10 text-center text-slate-400">Belum ada program sosial aktif.</div>}
            </div>
          </div>
        </section>


        <section id="karang-taruna" className="relative overflow-hidden bg-[#f6f8fc] py-24 text-slate-900">
          <div className="absolute right-[-8rem] top-[-8rem] h-80 w-80 rounded-full bg-blue-200/35 blur-3xl"/>
          <div className="relative mx-auto max-w-7xl px-5 sm:px-7 lg:px-8">
            <div className="grid gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:gap-20">
              <motion.div initial={{opacity:0,y:24}} whileInView={{opacity:1,y:0}} viewport={{once:true}} transition={{duration:.55}}>
                <BrandLogo className="h-24 w-24 shadow-lg shadow-blue-950/10"/>
                <p className="mt-7 text-xs font-black uppercase tracking-[0.22em] text-[#0b3b78]">Profil Organisasi</p>
                <h2 className="mt-4 text-3xl font-black tracking-tight text-[#07132f] md:text-5xl">Karang Taruna Kota Bandung</h2>
                <p className="mt-6 text-base leading-8 text-slate-600">Karang Taruna merupakan wadah pengembangan generasi muda yang berorientasi pada tanggung jawab sosial dan kesejahteraan masyarakat. Di Kota Bandung, perannya berkembang mengikuti kebutuhan masyarakat perkotaan yang dinamis, kreatif, dan kolaboratif.</p>
              </motion.div>

              <motion.div initial={{opacity:0,y:24}} whileInView={{opacity:1,y:0}} viewport={{once:true}} transition={{duration:.55,delay:.08}} className="border-t border-slate-300 lg:border-l lg:border-t-0 lg:pl-12">
                <p className="pt-7 text-xs font-black uppercase tracking-[0.2em] text-[#0b3b78] lg:pt-0">Asal-Usul dan Perkembangan</p>
                <div className="mt-6 space-y-7">
                  <div className="grid gap-2 sm:grid-cols-[8rem_1fr]"><div className="font-black text-[#07132f]">26 September 1960</div><p className="leading-7 text-slate-600">Karang Taruna lahir di Kampung Melayu, Jakarta. Awang Soma Dikarta bersama tokoh masyarakat setempat dan unsur Dinas Sosial memprakarsai wadah bagi anak yatim piatu, remaja putus sekolah, dan pemuda yang belum bekerja agar memperoleh ruang pembinaan sosial yang positif.</p></div>
                  <div className="grid gap-2 border-t border-slate-200 pt-6 sm:grid-cols-[8rem_1fr]"><div className="font-black text-[#07132f]">Perkembangan Bandung</div><p className="leading-7 text-slate-600">Perkembangan Karang Taruna di Kota Bandung mengikuti perluasan kelembagaan Karang Taruna secara nasional hingga tingkat kecamatan, kelurahan, RW, dan RT. Karakter Bandung sebagai kota pendidikan dan kota kreatif turut membentuk pola kegiatan kepemudaan yang adaptif.</p></div>
                  <div className="grid gap-2 border-t border-slate-200 pt-6 sm:grid-cols-[8rem_1fr]"><div className="font-black text-[#07132f]">Fokus Saat Ini</div><p className="leading-7 text-slate-600">Selain penanganan permasalahan kesejahteraan sosial, kegiatan Karang Taruna di Bandung berkembang pada pemberdayaan pemuda, ekonomi produktif dan UMKM, industri kreatif, seni budaya, serta kepedulian terhadap lingkungan.</p></div>
                </div>
              </motion.div>
            </div>

            <div className="mt-20 grid gap-12 border-t border-slate-300 pt-12 lg:grid-cols-2 lg:gap-20">
              <motion.div initial={{opacity:0,y:18}} whileInView={{opacity:1,y:0}} viewport={{once:true}} transition={{duration:.5}}>
                <p className="text-xs font-black uppercase tracking-[0.2em] text-[#0b3b78]">Visi Karang Taruna</p>
                <div className="mt-6 space-y-5 text-lg leading-8 text-slate-700"><p><span className="mr-3 font-black text-[#0b3b78]">01.</span>Membangun wadah pengembangan generasi muda yang adaptif terhadap perubahan zaman.</p><p><span className="mr-3 font-black text-[#0b3b78]">02.</span>Menjadi simpul pemuda yang kuat, tangguh secara mental, dan memiliki cita-cita yang selaras dengan visi Indonesia Emas.</p></div>
              </motion.div>
              <motion.div initial={{opacity:0,y:18}} whileInView={{opacity:1,y:0}} viewport={{once:true}} transition={{duration:.5,delay:.08}} className="lg:border-l lg:border-slate-300 lg:pl-12">
                <p className="text-xs font-black uppercase tracking-[0.2em] text-[#0b3b78]">Misi Karang Taruna</p>
                <div className="mt-6 space-y-5 text-base leading-8 text-slate-700"><p><span className="mr-3 font-black text-[#0b3b78]">01.</span>Memperkuat soliditas kelembagaan dan jejaring kerja sama internal hingga tingkat akar rumput, termasuk RW dan RT.</p><p><span className="mr-3 font-black text-[#0b3b78]">02.</span>Berperan aktif dalam program penanganan masalah sosial, pengembangan ekonomi produktif, dan kepedulian lingkungan.</p><p><span className="mr-3 font-black text-[#0b3b78]">03.</span>Menjadi mitra strategis pemerintah daerah dalam mendukung pembangunan kesejahteraan masyarakat.</p></div>
              </motion.div>
            </div>
          </div>
        </section>
        <section id="tentang" className="relative isolate min-h-[720px] overflow-hidden bg-[#07132f] py-24">
          <div className="absolute inset-0 bg-cover bg-center bg-no-repeat" style={{ backgroundImage:`url("${LANDING_COMMUNITY_IMAGE}")` }}/>
          <div className="absolute inset-0 bg-gradient-to-r from-[#061027]/98 via-[#07132f]/88 to-[#07132f]/30"/>
          <div className="absolute inset-0 bg-gradient-to-b from-[#071128] via-transparent to-[#030816]/95"/>
          <div className="relative z-10 mx-auto flex min-h-[580px] max-w-7xl items-center px-5 sm:px-7 lg:px-8">
            <motion.div initial={{ opacity:0, y:30 }} whileInView={{ opacity:1, y:0 }} viewport={{ once:true, margin:'-80px' }} transition={{ duration:.7 }} className="max-w-2xl">
              <p className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">SIAGA KARTA</p>
              <h2 className="mt-5 text-4xl font-light leading-tight tracking-tight md:text-5xl lg:text-6xl">Pelayanan warga yang <span className="block font-semibold text-cyan-100">jelas dan dapat dilacak.</span></h2>
              <p className="mt-7 max-w-xl text-base font-light leading-8 text-slate-200/85 md:text-lg">Permintaan warga masuk ke sistem, diproses petugas, lalu dapat dipantau melalui kode laporan. Untuk layanan terjadwal, sistem memeriksa konflik waktu ambulans dan pengemudi sebelum penugasan.</p>
              <div className="mt-10 grid max-w-xl border-y border-white/10 sm:grid-cols-2"><div className="py-5 sm:pr-6"><ShieldCheck className="h-6 w-6 text-cyan-300"/><p className="mt-4 font-bold text-white">Perlindungan data</p><p className="mt-2 text-sm leading-6 text-slate-300/75">Validasi identitas dilakukan oleh sistem dan data sensitif tidak ditampilkan kembali pada portal publik.</p></div><div className="border-t border-white/10 py-5 sm:border-l sm:border-t-0 sm:pl-6"><Users className="h-6 w-6 text-cyan-300"/><p className="mt-4 font-bold text-white">Akses responsif</p><p className="mt-2 text-sm leading-6 text-slate-300/75">Antarmuka dirancang untuk penggunaan pada ponsel, tablet, laptop, dan desktop.</p></div></div>
            </motion.div>
          </div>
        </section>
      </main>

      <footer className="border-t border-white/10 bg-[#030816] py-8">
        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-5 text-center sm:px-7 md:flex-row md:text-left lg:px-8">
          <div className="flex items-center gap-3"><BrandLogo className="h-10 w-10" light/><div><div className="text-sm font-bold text-white">SIAGA KARTA</div><div className="text-[10px] uppercase tracking-[0.18em] text-slate-500">Karang Taruna Kota Bandung · Pelayanan Warga</div></div></div>
          <div className="text-xs font-light text-slate-500"><p>© {new Date().getFullYear()} SIAGA KARTA. Sistem pelayanan warga terintegrasi.</p><p className="mt-1">Dikembangkan oleh <a href="https://github.com/TaupikApriansyah" target="_blank" rel="noreferrer" className="font-semibold text-cyan-300 hover:text-cyan-200">Taupik Apriansyah</a> · STMIK MARDIRA INDONESIA</p><p className="mt-1 text-[10px] text-slate-600">Logo Karang Taruna: Machiaavellis, CC BY-SA 4.0, Wikimedia Commons.</p></div>
          <button onClick={() => setRole('login')} className="text-xs font-bold text-cyan-300 hover:text-cyan-200">Masuk Portal Administrasi →</button>
        </div>
      </footer>

      <ModalForm isOpen={showReport} onClose={closeReport} title="Laporan & Pelayanan Warga">
        <form onSubmit={handleLaporSubmit} onChange={() => setReportDirty(true)} className="space-y-6" noValidate={false}>
          <input name="website" tabIndex={-1} autoComplete="off" className="hidden" aria-hidden="true"/>
          <div className="rounded-2xl border border-cyan-100 bg-cyan-50 p-4 text-sm leading-6 text-cyan-950">
            Isi semua field bertanda bintang. Jika data belum sesuai, pesan kesalahan akan tampil di form ini dan data tidak akan hilang.
          </div>
          {reportError && <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold leading-6 text-red-800"><AlertCircle className="mr-2 inline h-4 w-4"/>{reportError}</div>}
          <FieldGuide label="Kategori laporan" help="Pilih layanan yang paling sesuai agar laporan masuk ke alur petugas yang benar." required>
            <select value={reportCategory} onChange={(e)=>{setReportDirty(true); setReportCategory(e.target.value); setReportError(''); if(e.target.value!=='ambulans') setFormType('darurat');}} className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20">
              {REPORT_CATEGORIES.map(([value,label])=><option key={value} value={value}>{label}</option>)}
            </select>
          </FieldGuide>
          {reportCategory === 'ambulans' ? <>
            <div className={`flex items-start gap-3 rounded-2xl border p-4 text-sm ${formType === 'darurat' ? 'border-red-200 bg-red-50 text-red-800' : 'border-blue-200 bg-blue-50 text-blue-800'}`}>
              {formType === 'darurat' ? <AlertCircle className="mt-0.5 h-5 w-5 shrink-0"/> : <Clock className="mt-0.5 h-5 w-5 shrink-0"/>}
              <p>{formType === 'darurat' ? 'Darurat dipakai untuk kebutuhan respons cepat. Pastikan nomor HP aktif dan lokasi cukup rinci.' : 'Terjadwal membutuhkan waktu jemput dan foto KTP. Sistem akan mengecek benturan jadwal ambulans dan pengemudi.'}</p>
            </div>
            <div className="flex gap-2 rounded-xl bg-slate-100 p-1.5"><button type="button" onClick={() => { setReportDirty(true); setFormType('darurat'); }} className={`flex-1 rounded-lg py-2 text-sm font-bold ${formType==='darurat'?'bg-white text-red-700 shadow-sm':'text-slate-600 hover:text-slate-900'}`}>Darurat</button><button type="button" onClick={() => { setReportDirty(true); setFormType('terjadwal'); }} className={`flex-1 rounded-lg py-2 text-sm font-bold ${formType==='terjadwal'?'bg-white text-blue-700 shadow-sm':'text-slate-600 hover:text-slate-900'}`}>Terjadwal</button></div>
          </> : <div className="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">Pengaduan {categoryLabel(reportCategory)} akan masuk terlebih dahulu ke Karang Taruna Kelurahan, kemudian divalidasi Kecamatan sebelum dimonitor Karang Taruna Kota.</div>}

          <div><h3 className="mb-4 border-b border-slate-200 pb-2 text-xs font-black uppercase tracking-[0.16em] text-slate-700">Identitas Pelapor</h3><div className="grid gap-5 sm:grid-cols-2">
            <FieldGuide label="Nama lengkap" help="Nama pelapor yang dapat dikonfirmasi petugas." required><input name="name" required minLength={3} autoComplete="name" placeholder="Contoh: Budi Santoso" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
            <FieldGuide label="No. HP / WhatsApp Aktif" help="Nomor ini digunakan petugas untuk konfirmasi lapangan." required><input name="phone" required type="tel" inputMode="tel" pattern="(?:\+62|62|0)8[1-9][0-9]{6,11}" placeholder="08xxxxxxxxxx" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
            <FieldGuide label="Gmail / email warga" help="Kode pelacakan dan notifikasi penerimaan dikirim ke alamat email ini." required><input name="email" required type="email" autoComplete="email" placeholder="nama@gmail.com" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
            <FieldGuide label="Kelurahan" help="Pilih wilayah tempat pengaduan ditangani pertama kali." required><select name="region_id" required disabled={publicRegionsLoading} className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 disabled:bg-slate-100"><option value="">{publicRegionsLoading?'Memuat kelurahan...':'Pilih kelurahan'}</option>{publicRegions.map(r=><option key={r.id} value={r.id}>{r.name} · Kec. {r.parent?.name||'-'}</option>)}</select></FieldGuide>
            <FieldGuide label="Prioritas" help="Darurat, prioritas, dan reguler terpisah dari kategori pengaduan." required><select name="priority" value={reportPriority} onChange={e=>setReportPriority(e.target.value)} className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900">{REPORT_PRIORITIES.map(([value,label])=><option key={value} value={value}>{label}</option>)}</select></FieldGuide>
            <FieldGuide label="NIK 16 digit" help="Dipakai untuk validasi identitas dasar. NIK dilindungi dari salin dan tersembunyi secara default." required className="sm:col-span-2"><ProtectedNikInput name="nik" required pattern="[0-9]{16}" inputMode="numeric" maxLength={16} placeholder="16 digit angka" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-mono text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
          </div></div>

          {reportCategory === 'ambulans' && formType === 'terjadwal' && <FieldGuide label="Foto KTP" help="Format JPG, PNG, atau WEBP. Maksimal 4 MB. File hanya dipakai untuk administrasi layanan terjadwal." required><input name="ktp" required type="file" accept="image/jpeg,image/png,image/webp" className="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm text-slate-700"/></FieldGuide>}

          <div><h3 className="mb-4 border-b border-slate-200 pb-2 text-xs font-black uppercase tracking-[0.16em] text-slate-700">Detail Laporan</h3><div className="grid gap-5 sm:grid-cols-2">
            <FieldGuide label={reportCategory==='ambulans'?'Kondisi medis / keperluan ambulans':'Isi pengaduan'} help="Jelaskan inti masalah dan informasi yang diperlukan untuk verifikasi." required className="sm:col-span-2"><textarea name={reportCategory==='ambulans'?'medical_condition':'description'} required minLength={3} rows={3} placeholder={reportCategory==='ambulans'?(formType==='darurat'?'Contoh: pasien pingsan dan membutuhkan ambulans...':'Contoh: kontrol rumah sakit dan membutuhkan antar jemput...'):`Jelaskan pengaduan ${categoryLabel(reportCategory)} secara spesifik...`} className="w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
            {reportCategory==='ambulans' && formType==='terjadwal' && <FieldGuide label="Waktu penjemputan" help="Pilih waktu di masa mendatang. Jadwal final tetap menunggu pengecekan ketersediaan." required><input name="scheduled_at" required type="datetime-local" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>}
            {reportCategory==='ambulans' && formType==='terjadwal' && <FieldGuide label="Estimasi durasi" help="Perkiraan total waktu ambulans digunakan agar sistem dapat mencegah jadwal bertabrakan." required><select name="service_duration_minutes" defaultValue="120" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"><option value="60">1 jam</option><option value="120">2 jam</option><option value="180">3 jam</option><option value="240">4 jam</option><option value="360">6 jam</option></select></FieldGuide>}
            {reportCategory==='ambulans' && <FieldGuide label="Tujuan" help="Rumah sakit atau lokasi tujuan. Boleh dikosongkan jika belum diketahui." className="sm:col-span-2"><input name="destination" placeholder="Contoh: RSUD Kota" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>}
            <FieldGuide label={reportCategory==='ambulans'?'Lokasi penjemputan':'Lokasi / alamat terkait'} help={reportCategory==='ambulans'?'Wajib untuk penjemputan ambulans. Tulis alamat lengkap dan patokan.':'Opsional untuk kategori non-ambulans. Isi bila ada lokasi kejadian/objek yang perlu ditindaklanjuti.'} required={reportCategory==='ambulans'} className="sm:col-span-2"><textarea name="pickup_location" required={reportCategory==='ambulans'} minLength={reportCategory==='ambulans'?5:undefined} rows={3} placeholder={reportCategory==='ambulans'?'Alamat penjemputan, RT/RW, kelurahan, dan patokan':'Alamat/lokasi kejadian (opsional)'} className="w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
            <FieldGuide label="RT" help="Opsional; contoh 01."><input name="rt_number" maxLength={10} placeholder="01" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900"/></FieldGuide>
            <FieldGuide label="RW" help="Opsional; contoh 05."><input name="rw_number" maxLength={10} placeholder="05" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900"/></FieldGuide>
            <div className="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-bold text-slate-900">Koordinat lokasi pengaduan</p><p className="mt-1 text-xs text-slate-500">Opsional, tetapi sangat membantu marker realtime pada dashboard Kota.</p></div><Button type="button" size="sm" variant="outline" onClick={()=>navigator.geolocation?navigator.geolocation.getCurrentPosition(pos=>{setReportCoords({latitude:String(pos.coords.latitude),longitude:String(pos.coords.longitude)});addToast('Koordinat lokasi berhasil diambil.','success');},()=>addToast('Lokasi perangkat tidak dapat diakses.','error'),{enableHighAccuracy:true,timeout:10000}):addToast('Browser tidak mendukung geolocation.','error')}><MapPin className="mr-2 h-4 w-4"/>Ambil Lokasi</Button></div>{reportCoords.latitude&&<p className="mt-3 font-mono text-xs text-slate-700">{reportCoords.latitude}, {reportCoords.longitude}</p>}</div>
          </div></div>
          <div className="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end"><Button type="button" variant="ghost" onClick={closeReport}>Batal</Button><Button disabled={submitting} type="submit" variant={reportCategory==='ambulans'&&formType==='darurat'?'danger':'primary'}>{submitting?'Mengirim...':reportCategory==='ambulans'?(formType==='darurat'?'Kirim Permintaan Darurat':'Ajukan Penjadwalan'):'Kirim Pengaduan'}</Button></div>
        </form>
      </ModalForm>

      <ModalForm isOpen={showTrack} onClose={() => { setShowTrack(false); setTrackError(''); }} title="Periksa Status Layanan">
        <div className="space-y-5 text-slate-900">
          <p className="text-sm leading-6 text-slate-600">Masukkan kode laporan yang diberikan setelah pengajuan. Fitur pemeriksaan status pada navigasi, bagian utama, dan daftar layanan menggunakan alur data yang sama.</p>
          <FieldGuide label="Kode laporan" help="Contoh: SKB-ANDIR-2026-00001. Kode juga dikirim ke Gmail warga.">
            <div className="flex flex-col gap-3 sm:flex-row">
              <input value={trackCode} onChange={(e)=>{setTrackCode(normalizeTrackingCode(e.target.value));setTrackError('');}} onKeyDown={(e)=>{if(e.key==='Enter'){e.preventDefault();handleTrack();}}} placeholder="SKB-ANDIR-2026-00001" autoCapitalize="characters" spellCheck={false} className="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 font-mono text-sm font-semibold text-slate-950 caret-slate-950 outline-none placeholder:text-slate-500 focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/>
              <Button type="button" disabled={trackLoading} onClick={handleTrack}><Search className="mr-2 h-4 w-4"/>{trackLoading?'Memeriksa...':'Periksa'}</Button>
            </div>
          </FieldGuide>
          {trackError && <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold leading-6 text-red-800"><AlertCircle className="mr-2 inline h-4 w-4"/>{trackError}</div>}
          {trackResult && <div className="rounded-2xl border border-cyan-100 bg-cyan-50 p-5"><div className="grid gap-4 text-sm sm:grid-cols-2"><div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Kode</div><div className="mt-1 font-mono font-bold text-slate-900">{trackResult.code}</div></div><div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Status</div><div className="mt-1 font-bold uppercase text-cyan-900">{statusLabel(trackResult.status)}</div></div><div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Kategori</div><div className="mt-1 font-semibold text-slate-900">{categoryLabel(trackResult.category)}</div></div><div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Tahapan</div><div className="mt-1 font-semibold text-slate-900">{workflowLabel(trackResult.workflow_status)}</div></div>{trackResult.kelurahan&&<div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Wilayah</div><div className="mt-1 font-semibold text-slate-900">{trackResult.kelurahan} · Kec. {trackResult.kecamatan}</div></div>}<div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Jenis</div><div className="mt-1 font-semibold text-slate-900">{serviceTypeLabel(trackResult.type)}</div></div>{trackResult.scheduled_at && <div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Jadwal</div><div className="mt-1 font-semibold text-slate-900">{new Date(trackResult.scheduled_at).toLocaleString('id-ID')}</div></div>}{trackResult.ambulance && <div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Ambulans</div><div className="mt-1 font-semibold text-slate-900">{trackResult.ambulance}</div></div>}{trackResult.driver && <div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Pengemudi</div><div className="mt-1 font-semibold text-slate-900">{trackResult.driver}</div></div>}</div></div>}
        </div>
      </ModalForm>

      <ModalForm isOpen={showSuccess} onClose={() => setShowSuccess(false)} title="Laporan Berhasil Dikirim">
        <div className="py-4 text-center"><div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><CheckCircle2 className="h-10 w-10"/></div><h3 className="mt-5 text-2xl font-black text-slate-900">Simpan kode laporan Anda</h3><p className="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-600">Kode ini juga dikirim ke Gmail warga. Gunakan kode untuk memantau alur Kelurahan → Kecamatan → Kota dan jangan membagikannya kepada pihak yang tidak berkepentingan.</p><div className="mx-auto mt-6 max-w-md rounded-2xl border border-slate-200 bg-slate-50 p-5 font-mono text-lg font-black tracking-wider text-slate-900 sm:text-2xl">{trackCode}</div><div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row"><Button variant="outline" onClick={() => setShowSuccess(false)}>Tutup</Button><Button onClick={() => { setShowSuccess(false); openTrack(); }}>Periksa Status Sekarang</Button></div></div>
      </ModalForm>

      <ModalForm isOpen={showInfaq} onClose={() => { setShowInfaq(false); setInfaqCode(''); setInfaqError(''); }} title={infaqInfo.title || 'Infaq Siaga Karta'}>
        {!infaqInfo.active ? <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-800">Pembayaran infaq belum diaktifkan oleh pengelola.</div> : <div className="space-y-6">
          <div className="grid gap-6 md:grid-cols-2"><div className="rounded-2xl border border-slate-200 bg-white p-4 text-center">{infaqInfo.has_qr ? <img src={infaqInfo.qr_url} alt="QR pembayaran infaq SIAGA KARTA" className="mx-auto aspect-square w-full max-w-[280px] rounded-xl object-contain"/> : <div className="flex aspect-square items-center justify-center text-slate-400"><QrCode className="h-20 w-20"/></div>}<p className="mt-3 text-xs text-slate-500">Gunakan QR atau rekening resmi yang tersedia.</p></div><div>{infaqInfo.bank_name && infaqInfo.account_number && <div className="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-4"><div className="text-xs font-black uppercase tracking-wider text-blue-700">Transfer Bank</div><div className="mt-2 font-bold text-slate-950">{infaqInfo.bank_name}</div><div className="mt-1 flex items-center gap-2"><code className="rounded bg-white px-2 py-1 text-sm font-black text-slate-950">{infaqInfo.account_number}</code><button type="button" onClick={()=>navigator.clipboard?.writeText(infaqInfo.account_number).then(()=>addToast('Nomor rekening disalin.','success')).catch(()=>{})} className="text-xs font-bold text-blue-700">Salin</button></div><div className="mt-1 text-xs text-slate-600">a.n. {infaqInfo.account_name}</div></div>}<p className="text-sm leading-7 text-slate-600">{infaqInfo.description}</p>{infaqInfo.payment_instructions && <div className="mt-4 whitespace-pre-line rounded-xl bg-cyan-50 p-4 text-sm leading-6 text-cyan-950">{infaqInfo.payment_instructions}</div>}</div></div>
          {infaqCode ? <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center"><CheckCircle2 className="mx-auto h-8 w-8 text-emerald-600"/><div className="mt-2 font-bold text-emerald-900">Bukti pembayaran diterima</div><div className="mt-2 font-mono text-sm text-emerald-900">{infaqCode}</div><div className="mt-2 text-xs text-emerald-700">Pembayaran akan tercatat pada kas setelah diverifikasi pengelola SIAGA KARTA.</div></div> : <form onSubmit={handleInfaqSubmit} className="space-y-5">
            <input name="website" tabIndex={-1} autoComplete="off" className="hidden" aria-hidden="true"/>
            {infaqError && <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold leading-6 text-red-800"><AlertCircle className="mr-2 inline h-4 w-4"/>{infaqError}</div>}
            <div className="grid gap-5 sm:grid-cols-2">
              <FieldGuide label="Nama pembayar" help="Nama yang digunakan untuk pencatatan transaksi infaq." required><input name="payer_name" required minLength={3} placeholder="Nama lengkap" className="w-full rounded-xl border border-slate-300 p-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
              <FieldGuide label="No. HP Aktif" help="Nomor aktif untuk verifikasi jika ada kendala pada bukti pembayaran." required><input name="payer_phone" required type="tel" pattern="(?:\+62|62|0)8[1-9][0-9]{6,11}" placeholder="08xxxxxxxxxx" className="w-full rounded-xl border border-slate-300 p-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
              <FieldGuide label="Nominal infaq" help="Minimal Rp1.000. Isi angka tanpa titik atau koma." required className="sm:col-span-2"><input name="amount" required type="number" min="1000" placeholder="Contoh: 50000" className="w-full rounded-xl border border-slate-300 p-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
              <FieldGuide label="Catatan" help="Opsional. Bisa diisi tujuan atau pesan singkat untuk pencatatan." className="sm:col-span-2"><textarea name="description" rows={2} placeholder="Catatan opsional" className="w-full resize-none rounded-xl border border-slate-300 p-3 text-slate-900 outline-none focus:border-cyan-700 focus:ring-2 focus:ring-cyan-700/20"/></FieldGuide>
              <FieldGuide label="Bukti pembayaran" help="Upload JPG, PNG, atau WEBP maksimal 5 MB. Bukti yang sama tidak dapat dikirim dua kali." required className="sm:col-span-2"><input name="payment_proof" required type="file" accept="image/jpeg,image/png,image/webp" className="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm text-slate-700"/></FieldGuide>
            </div>
            <Button disabled={infaqSubmitting} type="submit" className="w-full"><Upload className="mr-2 h-4 w-4"/>{infaqSubmitting?'Mengirim bukti...':'Kirim Bukti Pembayaran'}</Button>
          </form>}
        </div>}
      </ModalForm>

      <SiagaBot db={db}/>
    </div>
  );
};


const Login = ({ setRole, addToast, onLogin, demo }) => {
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [loginValue,setLoginValue]=useState('');
  const [passwordValue,setPasswordValue]=useState('');
  const handleLogin = async (e) => {
    e.preventDefault(); setLoading(true);
    try {
      const { data } = await api.post('/auth/login', { login: loginValue, password: passwordValue });
      setToken(data.token,{expires_at:data.expires_at,absolute_expires_at:data.absolute_expires_at}); onLogin(data.user); setRole(data.user.role); addToast('Login berhasil.', 'success');
    } catch (err) { addToast(errorMessage(err), 'error'); } finally { setLoading(false); }
  };
  return (
  <div className="min-h-[100dvh] flex bg-[#FBFBFA] font-sans">
    <div className="hidden lg:block lg:w-1/2 relative overflow-hidden bg-[#07132f]">
      <img src="/hero-ambulance.png" className="absolute inset-0 w-full h-full object-cover mix-blend-overlay opacity-60" alt="Building" />
      <div className="absolute inset-0 bg-gradient-to-t from-[#07132f] via-[#07132f]/30 to-transparent"></div>
      <div className="absolute top-8 left-12 flex items-center gap-3"><BrandLogo className="h-11 w-11" light/><span className="text-white font-bold text-xl tracking-tight">SIAGA KARTA</span></div>
      <div className="absolute bottom-16 left-12 right-12"><h1 className="text-5xl font-black text-white mb-6 leading-[1.1] tracking-tight">Kelola Pelayanan <br/>Warga Secara Terpadu.</h1><p className="text-white/80 text-lg max-w-md font-medium leading-relaxed">Sistem administrasi Karang Taruna untuk pengelolaan pelayanan warga, ambulans, kas, dan tindak lanjut laporan secara terintegrasi.</p></div>
    </div>
    <div className="w-full lg:w-1/2 flex flex-col justify-center items-center p-8 relative bg-white">
      <div className="absolute top-6 right-6"><button onClick={() => setRole('warga')} className="px-5 py-2.5 bg-slate-50 text-slate-600 text-sm font-bold rounded-full border border-slate-200">Kembali ke Portal Warga</button></div>
      <div className="w-full max-w-sm"><h2 className="text-3xl font-black text-slate-900 mb-2">Portal Administrasi SIAGA KARTA</h2><p className="text-slate-500 mb-10 text-sm font-medium">Masuk menggunakan akun Kota, Kecamatan, atau Kelurahan yang telah terdaftar.</p>
        <form className="space-y-5" onSubmit={handleLogin}>
          {demo?.enabled && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-slate-900"><div className="text-xs font-black uppercase tracking-wider text-blue-700">Akun Demo</div><p className="mt-1 text-xs leading-5 text-slate-600">Akun demo yang sama berlaku pada localhost maupun Cloudflare. Pilih akun demo untuk mengisi formulir masuk secara otomatis.</p><div className="mt-3 grid grid-cols-2 gap-2">{(demo.usernames||['kota','kecamatan','kelurahan']).map(username=><button key={username} type="button" onClick={()=>{setLoginValue(username);setPasswordValue(demo.password||'');}} className="rounded-xl border border-blue-200 bg-white px-2 py-2 text-xs font-black capitalize text-blue-800 hover:bg-blue-100">{username}</button>)}</div></div>}
          <FieldGuide label="Email atau Nama Pengguna" help="Gunakan akun resmi Kota, Kecamatan, atau Kelurahan yang telah terdaftar." required><input name="login" required autoComplete="username" value={loginValue} onChange={e=>setLoginValue(e.target.value)} className="w-full px-4 py-3.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#0b3b78]/20 outline-none text-sm text-slate-900" /></FieldGuide>
          <FieldGuide label="Kata Sandi" help={demo?.enabled?'Kata sandi demo otomatis terisi setelah memilih akun.':'Masukkan kata sandi akun Anda. Tombol mata hanya mengubah tampilan dan tidak menyimpan kata sandi.'} required><div className="relative"><input name="password" required minLength={8} value={passwordValue} onChange={e=>setPasswordValue(e.target.value)} type={showPassword?'text':'password'} autoComplete="current-password" className="w-full px-4 py-3.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#0b3b78]/20 outline-none text-sm text-slate-900" /><button type="button" aria-label={showPassword?'Sembunyikan kata sandi':'Tampilkan kata sandi'} onClick={()=>setShowPassword(v=>!v)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500"><Eye className="w-5 h-5"/></button></div></FieldGuide>
          <button disabled={loading} type="submit" className="w-full py-4 bg-[#0b3b78] hover:bg-[#092f61] text-white rounded-xl font-bold disabled:opacity-50">{loading?'Memverifikasi...':'Masuk ke Dashboard'}</button>
        </form>
      </div>
    </div>
  </div>
  );
};

const NotificationBell = ({ addToast }) => {
  const [open,setOpen]=useState(false);
  const [items,setItems]=useState([]);
  const [unread,setUnread]=useState(0);
  const [loading,setLoading]=useState(false);
  const load=async(silent=false)=>{
    if(!silent)setLoading(true);
    try{
      const {data}=await api.get('/notifications',{params:{per_page:20}});
      setItems(data.data||[]);setUnread(Number(data.unread||0));
    }catch(err){if(!silent)addToast(errorMessage(err),'error');}
    finally{if(!silent)setLoading(false);}
  };
  useEffect(()=>{
    load(true);
    const changed=()=>load(true);
    window.addEventListener('siagakarta:notifications-changed',changed);
    return()=>window.removeEventListener('siagakarta:notifications-changed',changed);
  },[]);
  const markRead=async(item)=>{
    try{
      if(!item.read_at) await api.post(`/notifications/${item.id}/read`);
      setItems(rows=>rows.map(row=>row.id===item.id?{...row,read_at:row.read_at||new Date().toISOString()}:row));
      if(!item.read_at)setUnread(v=>Math.max(0,v-1));
      if(item.target_menu)window.dispatchEvent(new CustomEvent('siagakarta:navigate',{detail:item.target_menu}));
      setOpen(false);
    }catch(err){addToast(errorMessage(err),'error');}
  };
  const readAll=async()=>{
    try{await api.post('/notifications/read-all');setUnread(0);setItems(rows=>rows.map(row=>({...row,read_at:row.read_at||new Date().toISOString()})));}
    catch(err){addToast(errorMessage(err),'error');}
  };
  return <div className="relative">
    <button type="button" aria-label="Notifikasi" onClick={()=>{setOpen(v=>!v);if(!open)load();}} className="relative w-10 h-10 rounded-full bg-white border border-slate-200 text-slate-700 flex items-center justify-center hover:bg-slate-50 shadow-sm">
      <Bell className="w-5 h-5"/>
      {unread>0&&<span className="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-600 text-white text-[10px] font-black flex items-center justify-center border-2 border-white">{unread>99?'99+':unread}</span>}
    </button>
    {open&&<div className="absolute right-0 top-12 z-[120] w-[min(24rem,calc(100vw-2rem))] max-h-[32rem] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
      <div className="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-100"><div><div className="font-black text-slate-900">Notifikasi</div><div className="text-xs text-slate-500">{unread} belum dibaca</div></div>{unread>0&&<button type="button" onClick={readAll} className="text-xs font-bold text-emerald-700 hover:text-emerald-900">Baca semua</button>}</div>
      <div className="max-h-[26rem] overflow-y-auto">
        {loading?<div className="p-6 text-sm text-slate-500">Memuat notifikasi...</div>:items.length===0?<div className="p-6 text-sm text-slate-500">Belum ada notifikasi.</div>:items.map(item=><button type="button" key={item.id} onClick={()=>markRead(item)} className={`w-full text-left px-4 py-3 border-b border-slate-100 hover:bg-slate-50 ${item.read_at?'bg-white':'bg-emerald-50/60'}`}>
          <div className="flex gap-3"><span className={`mt-1.5 w-2 h-2 rounded-full shrink-0 ${item.read_at?'bg-slate-300':'bg-emerald-600'}`}/><div className="min-w-0"><div className="text-sm font-black text-slate-900">{item.title}</div><div className="mt-1 text-xs leading-5 text-slate-600">{item.message}</div><div className="mt-1 text-[11px] text-slate-400">{new Date(item.created_at).toLocaleString('id-ID')}</div></div></div>
        </button>)}
      </div>
    </div>}
  </div>;
};

const DashboardLayout = ({ role, currentUser, db, dashboardStats, updateDB, refreshDashboard, setRole, addToast, requestConfirm }) => {
  const allowedMenus = role==='kota'
    ? ['dashboard','pelayanan','ambulans','kas','laporan','users']
    : ['dashboard','pelayanan','laporan'];
  const [activeMenu, setActiveMenu] = useState(() => {
    const saved=sessionStorage.getItem(`siagakarta_menu_${role}`) || 'dashboard';
    return allowedMenus.includes(saved)?saved:'dashboard';
  });
  const [mobileOpen, setMobileOpen] = useState(false);
  const [desktopCompact, setDesktopCompact] = useState(false);

  const navMap = {
    dashboard:{ id:'dashboard',label:'Beranda',icon:Home },
    pelayanan:{ id:'pelayanan',label:'Pelayanan Warga',icon:Activity },
    ambulans:{ id:'ambulans',label:'Ambulans',icon:Truck },
    kas:{ id:'kas',label:'Kas & Pembayaran',icon:ReceiptText },
    laporan:{ id:'laporan',label:'Unduh Laporan',icon:Download },
    users:{ id:'users',label:'Manajemen Pengguna',icon:Users },
  };
  const sidebarNavs=allowedMenus.map(id=>navMap[id]);

  useEffect(() => {
    if (!allowedMenus.includes(activeMenu)) setActiveMenu('dashboard');
  }, [role, activeMenu]);

  const renderContent = () => {
    switch(activeMenu) {
      case 'dashboard': return <ViewDashboard db={db} role={role} stats={dashboardStats} addToast={addToast} refreshDashboard={refreshDashboard} />;
      case 'pelayanan': return <ViewPelayanan db={db} refreshDashboard={refreshDashboard} role={role} addToast={addToast} requestConfirm={requestConfirm} />;
      case 'ambulans': return <ViewAmbulans db={db} refreshDashboard={refreshDashboard} role={role} addToast={addToast} />;
      case 'kas': return <ViewKas db={db} refreshDashboard={refreshDashboard} role={role} addToast={addToast} />;
      case 'users': return <ViewUsers addToast={addToast} />;
      case 'laporan': return <ViewLaporan addToast={addToast} role={role} />;
      default: return <ViewDashboard db={db} role={role} stats={dashboardStats} addToast={addToast} refreshDashboard={refreshDashboard} />;
    }
  };
  const chooseMenu=(id)=>{
    if (!allowedMenus.includes(id)) return;
    if(id==='dashboard') refreshDashboard();
    setActiveMenu(id);
    sessionStorage.setItem(`siagakarta_menu_${role}`, id);
    setMobileOpen(false);
  };
  useEffect(()=>{
    const navigate=(event)=>{if(event.detail)chooseMenu(event.detail);};
    window.addEventListener('siagakarta:navigate',navigate);
    return()=>window.removeEventListener('siagakarta:navigate',navigate);
  },[role,activeMenu]);
  const logout=()=>requestConfirm('Logout','Yakin ingin keluar dari sistem?','Logout','Batal',async()=>{
    try{await api.post('/auth/logout');}catch{}finally{setToken(null);setRole('warga');addToast('Berhasil logout','info');}
  });
  const roleLabel=roleDisplay(role);
  const sidebarContent = (mobile=false) => <>
    <div className="flex items-center justify-between px-5 mb-8">
      <div className={`flex items-center gap-3 overflow-hidden whitespace-nowrap ${!mobile && desktopCompact ? 'lg:w-0 lg:opacity-0' : ''}`}>
        <BrandLogo className="h-11 w-11" light/>
        <span className="font-black text-white">SIAGA KARTA</span>
      </div>
      {mobile ? <button onClick={()=>setMobileOpen(false)} className="p-2 text-white/80"><X className="w-5 h-5"/></button> : <button onClick={()=>setDesktopCompact(v=>!v)} className="hidden lg:flex w-8 h-8 items-center justify-center rounded-full bg-white/5 hover:bg-white/10 text-white"><ChevronRight className={`w-4 h-4 ${desktopCompact?'':'rotate-180'}`}/></button>}
    </div>
    <nav className="flex-1 flex flex-col px-3 gap-2">
      {sidebarNavs.map(nav => <button key={nav.id} onClick={()=>chooseMenu(nav.id)} className={`flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all ${activeMenu===nav.id?'bg-white text-[#0b3b78] shadow-lg shadow-black/10':'text-white/70 hover:text-white hover:bg-white/5'}`} title={!mobile&&desktopCompact?nav.label:''}>
        <nav.icon className="w-5 h-5 shrink-0"/>{(mobile || !desktopCompact) && <span className="font-bold text-sm whitespace-nowrap">{nav.label}</span>}
      </button>)}
    </nav>
    <div className="p-3 mt-auto"><button onClick={logout} className="flex items-center gap-4 px-4 py-3.5 text-white/70 hover:text-white w-full rounded-xl hover:bg-white/5"><LogOut className="w-5 h-5 shrink-0"/>{(mobile || !desktopCompact) && <span className="font-bold text-sm">Keluar dari Sistem</span>}</button></div>
  </>;

  return <div className="min-h-[100dvh] bg-[#050b1c] lg:flex font-sans overflow-hidden">
    {mobileOpen && <button aria-label="Tutup menu" className="fixed inset-0 z-40 bg-black/50 lg:hidden" onClick={()=>setMobileOpen(false)}/>}
    <aside className={`fixed inset-y-0 left-0 z-50 w-72 py-6 flex flex-col bg-[#07132f] transition-transform lg:hidden ${mobileOpen?'translate-x-0':'-translate-x-full'}`}>{sidebarContent(true)}</aside>
    <aside className={`hidden lg:flex ${desktopCompact?'w-20':'w-64'} py-8 flex-col shrink-0 bg-gradient-to-b from-[#07132f] via-[#081a3d] to-[#050b1c] transition-all duration-300`}>{sidebarContent(false)}</aside>
    <main className="min-w-0 flex-1 flex flex-col h-[100dvh] overflow-hidden bg-[#f5f8ff] lg:rounded-l-3xl lg:shadow-[-10px_0_30px_rgba(0,0,0,0.2)]">
      <header className="min-h-16 sm:min-h-20 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-3 border-b border-blue-100 bg-white/90 backdrop-blur-md shrink-0">
        <div className="flex items-center gap-3 min-w-0"><button onClick={()=>setMobileOpen(true)} className="lg:hidden p-2 rounded-xl bg-slate-100 text-slate-700"><Menu className="w-5 h-5"/></button><div className="min-w-0"><h1 className="text-lg sm:text-2xl font-black text-slate-900 tracking-tight capitalize truncate">{navMap[activeMenu]?.label || 'Beranda'}</h1><p className="hidden sm:block text-sm text-slate-500 font-medium">Karang Taruna tingkat {roleLabel}{currentUser?.region?.name?` · ${currentUser.region.name}`:''} · sinkronisasi aktif</p></div></div>
        <div className="flex items-center gap-2"><NotificationBell addToast={addToast}/><div className="w-10 h-10 rounded-full bg-blue-100 text-[#0b3b78] flex items-center justify-center font-black border border-blue-200 shrink-0">{role==='kota'?'KO':role==='kecamatan'?'KC':'KL'}</div></div>
      </header>
      <div className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 hide-scroll"><DashboardErrorBoundary resetKey={activeMenu}><motion.div key={activeMenu} initial={{opacity:0,y:8}} animate={{opacity:1,y:0}} transition={{duration:.22,ease:'easeOut'}} className="min-h-full">{renderContent()}</motion.div></DashboardErrorBoundary></div>
    </main>
  </div>;
};

const reportMapColor = (marker) => {
  if(marker?.priority==='darurat') return '#dc2626';
  if(marker?.status==='ditolak') return '#0f172a';
  if(marker?.status==='selesai') return '#94a3b8';
  return ({bpjs:'#2563eb',ambulans:'#f97316',bantuan_sosial:'#16a34a',lansia_disabilitas:'#7c3aed',kesehatan:'#0891b2',kebencanaan:'#b45309'}[marker?.category] || '#64748b');
};
const escapeMapHtml=(value)=>String(value??'').replace(/[&<>"']/g,(ch)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
const normalizeAreaName=(value)=>String(value||'').toUpperCase().replace(/KELURAHAN|DESA|KEL\.|KEC\./g,'').replace(/[^A-Z0-9]/g,'');
const featureAreaName=(feature)=>{
  const p=feature?.properties||{};
  return p.NAMOBJ||p.WADMKD||p.KELURAHAN||p.kelurahan||p.NAMKEL||p.NAMA_KEL||p.NAMAKEL||p.nama_kelurahan||p.DESA_KELURAHAN||p.KEL_DESA||p.DESA||p.NAME_4||p.NAME_3||p.name||p.Name||'';
};

const KotaMapDashboard = ({ addToast }) => {
  const [mapData,setMapData]=useState(null);
  const [loading,setLoading]=useState(true);
  const [error,setError]=useState('');
  const [filters,setFilters]=useState({category:'',priority:'',kecamatan_id:'',kelurahan_id:'',status:'',date_from:'',date_to:''});
  const [detail,setDetail]=useState(null);
  const mapNodeRef=useRef(null);
  const mapRef=useRef(null);
  const polygonLayerRef=useRef(null);
  const markerLayerRef=useRef(null);
  const geoJsonCacheRef=useRef(null);
  const latestRequestRef=useRef(0);

  const paramsFromFilters=()=>Object.fromEntries(Object.entries(filters).filter(([,v])=>v!==''));
  const loadMapData=async({silent=false}={})=>{
    const rid=++latestRequestRef.current;
    if(!silent)setLoading(true);
    try{
      const {data}=await api.get('/dashboard/kota/map',{params:{...paramsFromFilters(),marker_limit:2500,_ts:Date.now()}});
      if(rid!==latestRequestRef.current)return;
      setMapData(data);setError('');
    }catch(err){if(rid===latestRequestRef.current){const msg=errorMessage(err);setError(msg);if(!silent)addToast?.(msg,'error');}}
    finally{if(rid===latestRequestRef.current&&!silent)setLoading(false);}
  };

  useEffect(()=>{loadMapData();},[filters.category,filters.priority,filters.kecamatan_id,filters.kelurahan_id,filters.status,filters.date_from,filters.date_to]);
  useEffect(()=>{
    const refresh=()=>loadMapData({silent:true});
    window.addEventListener('siagakarta:dashboard-refreshed',refresh);
    return()=>window.removeEventListener('siagakarta:dashboard-refreshed',refresh);
  },[filters]);

  const loadVillageDetail=async(regionId,fallback)=>{
    if(!regionId){setDetail(fallback||null);return;}
    try{const {data}=await api.get(`/dashboard/kota/kelurahan/${regionId}`,{params:paramsFromFilters()});setDetail(data.kelurahan);}catch(err){addToast?.(errorMessage(err),'error');}
  };

  useEffect(()=>{
    if(!mapData||!mapNodeRef.current)return;
    let cancelled=false;
    const draw=async()=>{
      let attempts=0;
      while(!window.L&&attempts<30&&!cancelled){await new Promise(r=>setTimeout(r,100));attempts++;}
      if(cancelled||!window.L)return;
      const L=window.L;
      if(!mapRef.current){
        mapRef.current=L.map(mapNodeRef.current,{zoomControl:true,minZoom:10}).setView([-6.9175,107.6191],12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(mapRef.current);
      }
      if(polygonLayerRef.current){mapRef.current.removeLayer(polygonLayerRef.current);polygonLayerRef.current=null;}
      if(markerLayerRef.current){mapRef.current.removeLayer(markerLayerRef.current);markerLayerRef.current=null;}

      if(!geoJsonCacheRef.current&&mapData.geojson?.url){
        try{const response=await fetch(mapData.geojson.url,{cache:'force-cache'});if(!response.ok)throw new Error('GeoJSON tidak dapat dimuat');geoJsonCacheRef.current=await response.json();}
        catch(e){if(!cancelled)setError('Batas wilayah GeoJSON Kota Bandung belum dapat dimuat. Statistik database tetap tersedia.');}
      }
      if(cancelled)return;
      const statsByName=new Map((mapData.kelurahan_stats||[]).flatMap(v=>[[normalizeAreaName(v.name),v],[normalizeAreaName(v.geojson_name),v]].filter(([k])=>k)));
      if(geoJsonCacheRef.current){
        polygonLayerRef.current=L.geoJSON(geoJsonCacheRef.current,{
          style:(feature)=>{
            const item=statsByName.get(normalizeAreaName(featureAreaName(feature)));
            const total=Number(item?.total||0);
            const fillColor=total>=100?'#1e3a8a':total>=50?'#1d4ed8':total>=20?'#3b82f6':total>0?'#93c5fd':'#e2e8f0';
            return {color:'#475569',weight:.7,fillColor,fillOpacity:.48};
          },
          onEachFeature:(feature,layer)=>{
            const featureName=featureAreaName(feature)||'Kelurahan';
            const item=statsByName.get(normalizeAreaName(featureName));
            const categorySummary=(item?.categories||[]).slice(0,3).map(v=>`${escapeMapHtml(categoryLabel(v.category))}: ${Number(v.percentage||0).toLocaleString('id-ID')}%`).join('<br>');
            layer.bindTooltip(`<strong>${escapeMapHtml(item?.name||featureName)}</strong><br>Total pengaduan: ${Number(item?.total||0).toLocaleString('id-ID')}${categorySummary?`<br>${categorySummary}`:''}`,{sticky:true});
            layer.on('click',()=>loadVillageDetail(item?.id,item||{name:featureName,total:0,categories:[],latest:[]}));
          }
        }).addTo(mapRef.current);
        try{if(polygonLayerRef.current.getBounds().isValid())mapRef.current.fitBounds(polygonLayerRef.current.getBounds(),{padding:[10,10],maxZoom:13});}catch{}
      }

      const markerLayer=typeof L.markerClusterGroup==='function'?L.markerClusterGroup({chunkedLoading:true,showCoverageOnHover:false,maxClusterRadius:45}):L.layerGroup();
      (mapData.markers||[]).forEach(marker=>{
        const lat=Number(marker.latitude),lng=Number(marker.longitude);if(!Number.isFinite(lat)||!Number.isFinite(lng))return;
        const pin=L.circleMarker([lat,lng],{radius:7,color:'#fff',weight:2,fillColor:reportMapColor(marker),fillOpacity:.95});
        pin.bindPopup(`<div style="min-width:220px"><strong>${escapeMapHtml(marker.id)}</strong><br>${escapeMapHtml(categoryLabel(marker.category))} · ${escapeMapHtml(priorityLabel(marker.priority))}<br>Status: ${escapeMapHtml(statusLabel(marker.status))}<br>Alur: ${escapeMapHtml(workflowLabel(marker.workflow_status))}<br>${escapeMapHtml(marker.kelurahan||'-')}, ${escapeMapHtml(marker.kecamatan||'-')}<br>${escapeMapHtml(marker.location||'-')}<br><small>${escapeMapHtml(new Date(marker.time).toLocaleString('id-ID'))}<br>${lat.toFixed(6)}, ${lng.toFixed(6)}</small></div>`);
        markerLayer.addLayer(pin);
      });
      markerLayer.addTo(mapRef.current);markerLayerRef.current=markerLayer;
      setTimeout(()=>mapRef.current?.invalidateSize(),50);
    };
    draw();
    return()=>{cancelled=true;};
  },[mapData]);

  useEffect(()=>()=>{if(mapRef.current){mapRef.current.remove();mapRef.current=null;}},[]);
  const stat=mapData?.stats||{};
  const districts=mapData?.filters?.kecamatan||[];
  const villages=(mapData?.filters?.kelurahan||[]).filter(v=>!filters.kecamatan_id||String(v.parent_id)===String(filters.kecamatan_id));
  const statCards=[
    ['Total Pengaduan Kota Bandung',stat.total||0],['Pengaduan Hari Ini',stat.today||0],['Dalam Proses',stat.processing||0],['Selesai',stat.completed||0],
    ['Pengaduan Darurat',stat.emergency||0],['Kelurahan Terbanyak',`${stat.top_kelurahan||'-'}${stat.top_kelurahan_total?` · ${stat.top_kelurahan_total}`:''}`],['Kategori Terbanyak',`${categoryLabel(stat.top_category)}${stat.top_category_total?` · ${stat.top_category_total}`:''}`]
  ];
  const chartColors=['#2563eb','#f97316','#16a34a','#7c3aed','#0891b2','#dc2626','#64748b','#0f766e','#a16207','#334155'];

  return <div className="space-y-6">
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-black uppercase tracking-[.18em] text-blue-700">Monitoring realtime tingkat Kota</p><h2 className="mt-1 text-2xl font-black text-slate-950">Peta Sebaran Pengaduan Kota Bandung</h2><p className="mt-1 text-sm text-slate-600">Marker, statistik kelurahan, dan persentase dihitung dari database. Pembaruan mengikuti sinkronisasi sistem tanpa refresh browser.</p></div><Button size="sm" variant="outline" disabled={loading} onClick={()=>loadMapData()}><RefreshCw className={`mr-2 h-4 w-4 ${loading?'animate-spin':''}`}/>Sinkronkan</Button></div>
    <div className="grid gap-3 md:grid-cols-4 xl:grid-cols-7">{statCards.map(([label,value])=><Card key={label} className="p-4"><div className="text-xs font-bold leading-5 text-slate-500">{label}</div><div className="mt-2 text-xl font-black text-slate-950 break-words">{typeof value==='number'?value.toLocaleString('id-ID'):value}</div></Card>)}</div>
    <Card className="p-4 sm:p-5">
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <select value={filters.category} onChange={e=>setFilters(f=>({...f,category:e.target.value}))} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900"><option value="">Semua kategori</option>{REPORT_CATEGORIES.map(([v,l])=><option key={v} value={v}>{l}</option>)}</select>
        <select value={filters.priority} onChange={e=>setFilters(f=>({...f,priority:e.target.value}))} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900"><option value="">Semua prioritas</option>{REPORT_PRIORITIES.map(([v,l])=><option key={v} value={v}>{l}</option>)}</select>
        <select value={filters.kecamatan_id} onChange={e=>setFilters(f=>({...f,kecamatan_id:e.target.value,kelurahan_id:''}))} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900"><option value="">Semua kecamatan</option>{districts.map(v=><option key={v.id} value={v.id}>{v.name}</option>)}</select>
        <select value={filters.kelurahan_id} onChange={e=>setFilters(f=>({...f,kelurahan_id:e.target.value}))} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900"><option value="">Semua kelurahan</option>{villages.map(v=><option key={v.id} value={v.id}>{v.name}</option>)}</select>
        <select value={filters.status} onChange={e=>setFilters(f=>({...f,status:e.target.value}))} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900"><option value="">Semua status</option><option value="menunggu">Menunggu</option><option value="diproses">Diproses</option><option value="dijemput">Dijemput</option><option value="selesai">Selesai</option><option value="ditolak">Ditolak</option></select>
        <button type="button" onClick={()=>setFilters({category:'',priority:'',kecamatan_id:'',kelurahan_id:'',status:'',date_from:'',date_to:''})} className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Reset filter</button>
      </div><div className="mt-3 grid gap-3 sm:grid-cols-2"><FieldGuide label="Dari tanggal"><input type="date" value={filters.date_from} onChange={e=>setFilters(f=>({...f,date_from:e.target.value}))} className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"/></FieldGuide><FieldGuide label="Sampai tanggal"><input type="date" value={filters.date_to} onChange={e=>setFilters(f=>({...f,date_to:e.target.value}))} className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"/></FieldGuide></div>
    </Card>
    {error&&<div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">{error}</div>}
    {Number(stat.unmapped_reports||0)>0&&<div className="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950"><b>{Number(stat.unmapped_reports).toLocaleString('id-ID')} pengaduan historis belum memiliki relasi Kelurahan.</b> Data tersebut tetap tersimpan, tetapi tidak ditempatkan ke peta sampai wilayahnya dilengkapi. Sistem tidak menebak wilayah secara otomatis.</div>}
    <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
      <Card noPadding className="relative min-h-[560px] overflow-hidden"><div ref={mapNodeRef} className="h-[560px] w-full bg-slate-100"/>{loading&&<div className="absolute inset-0 z-[500] flex items-center justify-center bg-white/55 backdrop-blur-[1px]"><div className="rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow">Memuat data peta...</div></div>}<div className="absolute bottom-3 left-3 z-[500] rounded-xl bg-white/95 px-3 py-2 text-[11px] font-semibold text-slate-600 shadow">Marker: {(mapData?.markers||[]).length.toLocaleString('id-ID')} / {(mapData?.marker_count||0).toLocaleString('id-ID')}{mapData?.markers_truncated?' · clustering + batas performa aktif':''}</div><div className="absolute right-3 top-3 z-[500] hidden max-w-[230px] rounded-xl border border-slate-200 bg-white/95 p-3 text-[10px] font-semibold text-slate-700 shadow-lg backdrop-blur sm:block"><div className="mb-2 text-[11px] font-black uppercase tracking-wider text-slate-900">Legenda marker</div><div className="grid grid-cols-2 gap-x-3 gap-y-1.5">{[['#dc2626','Darurat'],['#2563eb','BPJS'],['#f97316','Ambulans'],['#16a34a','Bantuan Sosial'],['#7c3aed','Disabilitas/Lansia'],['#94a3b8','Selesai'],['#0f172a','Ditolak'],['#64748b','Kategori lain']].map(([color,label])=><div key={label} className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 shrink-0 rounded-full border border-white shadow" style={{backgroundColor:color}}/><span>{label}</span></div>)}</div><div className="mt-3 border-t border-slate-200 pt-2 text-[9px] leading-4 text-slate-500">Warna area menunjukkan kepadatan pengaduan kelurahan: semakin gelap, semakin tinggi jumlah pengaduan pada filter aktif.</div></div></Card>
      <Card className="min-h-[560px]"><div className="flex items-center gap-3"><div className="rounded-xl bg-blue-50 p-2 text-blue-700"><Building2 className="h-5 w-5"/></div><div><h3 className="font-black text-slate-950">Detail Kelurahan</h3><p className="text-xs text-slate-500">Klik batas kelurahan pada peta.</p></div></div>{detail?<div className="mt-5 space-y-5"><div><div className="text-xl font-black text-slate-950">{detail.name}</div><div className="text-sm text-slate-500">{detail.kecamatan?`Kecamatan ${detail.kecamatan}`:'Wilayah Kota Bandung'}</div><div className="mt-3 text-3xl font-black text-blue-800">{Number(detail.total||0).toLocaleString('id-ID')}</div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Total pengaduan</div></div><div className="overflow-hidden rounded-xl border border-slate-200"><table className="w-full text-xs"><thead className="bg-slate-50 text-slate-600"><tr><th className="p-2 text-left">Kategori</th><th className="p-2 text-right">Jumlah</th><th className="p-2 text-right">%</th></tr></thead><tbody>{(detail.categories||[]).length?(detail.categories||[]).map(v=><tr key={v.category} className="border-t border-slate-100"><td className="p-2 font-semibold text-slate-800">{categoryLabel(v.category)}</td><td className="p-2 text-right">{v.total}</td><td className="p-2 text-right font-bold">{v.percentage}%</td></tr>):<tr><td colSpan={3} className="p-4 text-center text-slate-500">Belum ada pengaduan pada filter ini.</td></tr>}</tbody></table></div>{detail.rt_count!=null&&<div className="rounded-xl bg-slate-50 p-3 text-xs font-semibold text-slate-700">Struktur lokal: {detail.rt_count} RT · {detail.rw_count} RW</div>}<div><div className="mb-2 text-xs font-black uppercase tracking-wider text-slate-500">Pengaduan terbaru</div><div className="space-y-2 max-h-52 overflow-y-auto">{(detail.latest||[]).length?(detail.latest||[]).map(v=><div key={v.code||v.id} className="rounded-xl border border-slate-100 p-3"><div className="font-mono text-xs font-black text-slate-900">{v.code||v.id}</div><div className="mt-1 text-xs text-slate-600">{categoryLabel(v.category)} · {workflowLabel(v.workflow_status)}</div></div>):<p className="text-xs text-slate-500">Belum ada data terbaru.</p>}</div></div></div>:<div className="mt-8 rounded-2xl bg-slate-50 p-6 text-center text-sm leading-6 text-slate-500">Klik salah satu wilayah kelurahan untuk melihat jumlah, persentase kategori, dan laporan terbaru.</div>}</Card>
    </div>
    <div className="grid gap-6 lg:grid-cols-2"><Card><h3 className="font-black text-slate-950">Distribusi Pengaduan Kota Bandung</h3><div className="mt-4 h-72"><ResponsiveContainer width="100%" height="100%"><PieChart><Pie data={mapData?.category_distribution||[]} dataKey="total" nameKey="category" innerRadius={52} outerRadius={90} paddingAngle={2}>{(mapData?.category_distribution||[]).map((v,i)=><Cell key={v.category} fill={chartColors[i%chartColors.length]}/>)}</Pie><RechartsTooltip formatter={(value,name,item)=>[`${value} (${item?.payload?.percentage||0}%)`,categoryLabel(item?.payload?.category)]}/></PieChart></ResponsiveContainer></div><div className="grid grid-cols-2 gap-2 text-xs">{(mapData?.category_distribution||[]).map((v,i)=><div key={v.category} className="flex items-center justify-between rounded-lg bg-slate-50 p-2"><span>{categoryLabel(v.category)}</span><b>{v.percentage}%</b></div>)}</div></Card><Card><h3 className="font-black text-slate-950">Top 5 Kelurahan dengan Pengaduan Terbanyak</h3><div className="mt-4 h-80"><ResponsiveContainer width="100%" height="100%"><BarChart data={mapData?.top_kelurahan||[]} layout="vertical" margin={{left:30,right:20}}><CartesianGrid strokeDasharray="3 3" horizontal={false}/><XAxis type="number" allowDecimals={false}/><YAxis type="category" dataKey="name" width={105} tick={{fontSize:11}}/><RechartsTooltip/><Bar dataKey="total" fill="#0b3b78" radius={[0,8,8,0]}/></BarChart></ResponsiveContainer></div></Card></div>
  </div>;
};

const KelurahanStructureCard=({stats,addToast})=>{
  const region=stats?.region;
  const [rt,setRt]=useState(region?.rt_count??11);const [rw,setRw]=useState(region?.rw_count??11);const [saving,setSaving]=useState(false);
  useEffect(()=>{setRt(region?.rt_count??11);setRw(region?.rw_count??11);},[region?.id,region?.rt_count,region?.rw_count]);
  if(!region?.id)return null;
  const save=async()=>{setSaving(true);try{const {data}=await api.patch(`/regions/${region.id}/local-structure`,{rt_count:Number(rt),rw_count:Number(rw)});addToast?.(data.message||'Struktur RT/RW diperbarui.','success');}catch(e){addToast?.(errorMessage(e),'error');}finally{setSaving(false);}};
  return <Card><div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><h3 className="font-black text-slate-950">Struktur Wilayah Kelurahan</h3><p className="mt-1 text-sm text-slate-600">Default pilot adalah 11 RT dan 11 RW. Pengelola Kelurahan dapat menyesuaikan jumlahnya sendiri.</p></div><div className="grid grid-cols-2 gap-3 sm:w-72"><FieldGuide label="Jumlah RT"><input type="number" min="0" max="999" value={rt} onChange={e=>setRt(e.target.value)} className="w-full rounded-xl border border-slate-300 px-3 py-2 text-slate-900"/></FieldGuide><FieldGuide label="Jumlah RW"><input type="number" min="0" max="999" value={rw} onChange={e=>setRw(e.target.value)} className="w-full rounded-xl border border-slate-300 px-3 py-2 text-slate-900"/></FieldGuide></div><Button disabled={saving} onClick={save}>{saving?'Menyimpan...':'Simpan Struktur'}</Button></div></Card>;
};

const ViewDashboard = ({ db, role, stats, addToast, refreshDashboard }) => {
  const canFinance=role==='kota';
  const canOperations=STAFF_ROLES.includes(role);
  const liveChart=stats?.daily?.length ? stats.daily : emptyChartData;
  const pendingFinance=Number(stats?.finance_pending||0);
  const actionLabel=(action='')=>action.replaceAll('.',' · ').replaceAll('_',' ');
  useEffect(()=>{
    const onChange=()=>refreshDashboard();
    window.addEventListener('siagakarta:operations-changed',onChange);
    if(role==='kota') window.addEventListener('siagakarta:finance-changed',onChange);
    return()=>{
      window.removeEventListener('siagakarta:operations-changed',onChange);
      window.removeEventListener('siagakarta:finance-changed',onChange);
    };
  },[role]);
  return (
    <motion.div initial={{opacity:0,y:10}} animate={{opacity:1,y:0}} transition={{duration:.28}} className="flex flex-col h-full gap-6 max-w-[1600px]">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        {canFinance&&<Card className="col-span-1 md:col-span-2 bg-gradient-to-br from-[#0b3b78] via-[#092f61] to-[#07132f] text-white border-none shadow-xl shadow-blue-950/15 transition-transform duration-200 hover:-translate-y-0.5"><h3 className="text-cyan-100 text-sm font-bold mb-1 uppercase tracking-widest">Saldo Kas Terverifikasi</h3><div className="text-4xl font-black mb-4 tracking-tight text-white">Rp {Number(stats?.saldo||0).toLocaleString('id-ID')}</div><div className="flex gap-6 text-sm"><div><div className="text-blue-200">Pemasukan bulan ini</div><div className="font-bold text-lg">Rp{Number(stats?.pemasukan_bulan||0).toLocaleString('id-ID')}</div></div><div><div className="text-blue-200">Pengeluaran bulan ini</div><div className="font-bold text-lg">Rp{Number(stats?.pengeluaran_bulan||0).toLocaleString('id-ID')}</div></div></div></Card>}
        {canOperations&&<Card className="flex flex-col justify-center"><div className="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mb-4"><Activity className="w-6 h-6"/></div><div className="text-3xl font-black text-slate-900">{stats?.laporan_aktif ?? 0}</div><div className="text-sm font-bold text-slate-500">Laporan Aktif</div></Card>}
        {role==='kota'&&<Card className="flex flex-col justify-center"><div className="w-12 h-12 bg-cyan-100 text-cyan-700 rounded-2xl flex items-center justify-center mb-4"><Truck className="w-6 h-6"/></div><div className="text-3xl font-black text-slate-900">{stats?.ambulans_tersedia ?? 0}</div><div className="text-sm font-bold text-slate-500">Ambulans Tersedia</div></Card>}
        {canFinance&&<Card className="flex flex-col justify-center"><div className="w-12 h-12 bg-amber-100 text-amber-700 rounded-2xl flex items-center justify-center mb-4"><Clock className="w-6 h-6"/></div><div className="text-3xl font-black text-slate-900">{pendingFinance}</div><div className="text-sm font-bold text-slate-500">Menunggu Verifikasi</div></Card>}
      </div>
      {role==='kota'&&<KotaMapDashboard addToast={addToast}/>}
      {role==='kelurahan'&&<KelurahanStructureCard stats={stats} addToast={addToast}/>}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 min-h-[350px]">
        {canOperations&&<Card className="lg:col-span-2 flex flex-col"><div className="flex justify-between items-center mb-6"><h3 className="font-bold text-slate-900">Volume Laporan 7 Hari</h3><span className="text-xs font-bold text-blue-700">SINKRONISASI AKTIF</span></div><div className="flex-1 w-full min-h-[250px]"><ResponsiveContainer width="100%" height="100%"><AreaChart data={liveChart} margin={{top:10,right:10,left:-20,bottom:0}}><CartesianGrid strokeDasharray="3 3" vertical={false}/><XAxis dataKey="name" axisLine={false} tickLine={false}/><YAxis axisLine={false} tickLine={false}/><RechartsTooltip/><Area type="monotone" name="Darurat" dataKey="darurat" stroke="#ef4444" strokeWidth={3} fillOpacity={.08} fill="#ef4444"/><Area type="monotone" name="Prioritas/Reguler" dataKey="sosial" stroke="#0b3b78" strokeWidth={3} fillOpacity={.08} fill="#0b3b78"/></AreaChart></ResponsiveContainer></div></Card>}
        <Card className="flex flex-col bg-slate-50/50 border-none"><div className="flex items-center justify-between mb-6"><h3 className="font-bold text-slate-900">Aktivitas Terkini</h3><span className="text-[10px] font-black uppercase tracking-widest text-slate-400">Audit</span></div><div className="flex-1 overflow-y-auto pr-2 space-y-5">{(stats?.activity||[]).length?stats.activity.map(item=><div key={item.id} className="flex gap-3"><div className="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-500 shrink-0"/><div><p className="text-sm font-semibold text-slate-800 capitalize">{actionLabel(item.action)}</p><p className="mt-1 text-xs text-slate-400">{item.actor||'Sistem'} • {new Date(item.created_at).toLocaleString('id-ID')}</p></div></div>):<p className="text-sm text-slate-500">Belum ada aktivitas tercatat.</p>}</div></Card>
      </div>
    </motion.div>
  );
};

const ViewPelayanan = ({ db, refreshDashboard, role, addToast, requestConfirm }) => {
  const [showInputModal,setShowInputModal]=useState(false);
  const [inputType,setInputType]=useState('darurat');
  const [inputCategory,setInputCategory]=useState('ambulans');
  const [inputPriority,setInputPriority]=useState('reguler');
  const [allowedRegions,setAllowedRegions]=useState([]);
  const [allowedRegionsLoading,setAllowedRegionsLoading]=useState(false);
  const [manualCoords,setManualCoords]=useState({latitude:'',longitude:''});
  const [detail,setDetail]=useState(null);const [detailLoading,setDetailLoading]=useState(false);const detailRequestRef=useRef(0);
  const [manualSubmitting,setManualSubmitting]=useState(false);const [manualDirty,setManualDirty]=useState(false);const manualRequestUuidRef=useRef(newRequestUuid());
  const [decisionTarget,setDecisionTarget]=useState(null);const [opdTarget,setOpdTarget]=useState(null);const [actionSubmitting,setActionSubmitting]=useState(false);
  const [reportRows,setReportRows]=useState([]);const [reportPage,setReportPage]=useState(1);const [reportMeta,setReportMeta]=useState({current_page:1,last_page:1,total:0});
  const [reportStatusFilter,setReportStatusFilter]=useState('');const [reportCategoryFilter,setReportCategoryFilter]=useState('');const [workflowFilter,setWorkflowFilter]=useState('');const [reportLoading,setReportLoading]=useState(false);

  const loadRegions=async()=>{if(allowedRegionsLoading)return;setAllowedRegionsLoading(true);try{const {data}=await api.get('/regions/allowed-kelurahan');setAllowedRegions(data.regions||[]);}catch(err){setAllowedRegions([]);addToast(errorMessage(err),'error');}finally{setAllowedRegionsLoading(false);}};
  useEffect(()=>{setAllowedRegions([]);},[role]);
  const loadReports=async()=>{setReportLoading(true);try{const {data}=await api.get('/reports',{params:{page:reportPage,per_page:20,status:reportStatusFilter||undefined,category:reportCategoryFilter||undefined,workflow_status:workflowFilter||undefined}});setReportRows((data.data||[]).map(mapReportRow));setReportMeta({current_page:data.current_page||1,last_page:data.last_page||1,total:data.total||0});}catch(err){addToast(errorMessage(err),'error');}finally{setReportLoading(false);}};
  useEffect(()=>{loadReports();const onRefresh=()=>loadReports();window.addEventListener('siagakarta:operations-changed',onRefresh);return()=>window.removeEventListener('siagakarta:operations-changed',onRefresh);},[reportPage,reportStatusFilter,reportCategoryFilter,workflowFilter,role]);
  useEffect(()=>{if(!manualDirty)return;const warn=e=>{e.preventDefault();e.returnValue='';};window.addEventListener('beforeunload',warn);return()=>window.removeEventListener('beforeunload',warn);},[manualDirty]);
  const closeManual=()=>{if(!manualDirty){setShowInputModal(false);return;}requestConfirm('Buang perubahan?','Data pengaduan yang sudah diisi belum disimpan.','Buang','Kembali',()=>{setManualDirty(false);setShowInputModal(false);},'danger');};
  const openManual=()=>{manualRequestUuidRef.current=newRequestUuid();setManualDirty(false);setInputCategory('ambulans');setInputPriority('reguler');setInputType('darurat');setManualCoords({latitude:'',longitude:''});if(!allowedRegions.length)loadRegions();setShowInputModal(true);};
  const handleManualInputSubmit=async(e)=>{e.preventDefault();const form=e.currentTarget;if(!form.checkValidity()){form.reportValidity();return;}setManualSubmitting(true);try{const payload=Object.fromEntries(new FormData(form).entries());payload.request_uuid=manualRequestUuidRef.current;payload.category=inputCategory;payload.priority=inputPriority;if(inputCategory==='ambulans') payload.type=inputType; else delete payload.type;if(manualCoords.latitude&&manualCoords.longitude)Object.assign(payload,manualCoords);const {data}=await api.post('/reports/manual',payload);addToast(`${data.message} Kode: ${data.code}`,'success');form.reset();setManualDirty(false);setShowInputModal(false);manualRequestUuidRef.current=newRequestUuid();await loadReports();}catch(err){addToast(errorMessage(err),'error');}finally{setManualSubmitting(false);}};
  const openDetail=async(code)=>{const rid=++detailRequestRef.current;setDetail({code});setDetailLoading(true);try{const {data}=await api.get(`/reports/${code}`);if(rid===detailRequestRef.current)setDetail(data.report);}catch(err){if(rid===detailRequestRef.current){setDetail(null);addToast(errorMessage(err),'error');}}finally{if(rid===detailRequestRef.current)setDetailLoading(false);}};
  const closeDetail=()=>{detailRequestRef.current++;setDetail(null);setDetailLoading(false);};
  const afterAction=async(msg)=>{addToast(msg,'success');await loadReports();};
  const forwardKecamatan=code=>requestConfirm('Ajukan ke Kecamatan?','Pastikan Kelurahan telah melakukan verifikasi awal. Pengaduan akan masuk ke Kecamatan untuk validasi dan cross-check.','Ajukan','Batal',async()=>{try{const {data}=await api.post(`/reports/${code}/forward-kecamatan`,{});await afterAction(data.message);}catch(e){addToast(errorMessage(e),'error');}},'success');
  const validateKecamatan=code=>requestConfirm('Validasi dan kirim ke Kota?','Dengan konfirmasi ini, Kecamatan menyatakan data telah di-cross-check dan sesuai untuk diteruskan ke Karang Taruna Kota.','Validasi','Batal',async()=>{try{const {data}=await api.post(`/reports/${code}/kecamatan-decision`,{decision:'validate'});await afterAction(data.message);}catch(e){addToast(errorMessage(e),'error');}},'success');
  const submitDecision=async(e)=>{e.preventDefault();setActionSubmitting(true);try{const payload=Object.fromEntries(new FormData(e.currentTarget).entries());payload.decision=decisionTarget.decision;const {data}=await api.post(`/reports/${decisionTarget.code}/kecamatan-decision`,payload);setDecisionTarget(null);await afterAction(data.message);}catch(err){addToast(errorMessage(err),'error');}finally{setActionSubmitting(false);}};
  const submitOpd=async(e)=>{e.preventDefault();setActionSubmitting(true);try{const payload=Object.fromEntries(new FormData(e.currentTarget).entries());const {data}=await api.post(`/reports/${opdTarget}/forward-opd`,payload);setOpdTarget(null);await afterAction(data.message);}catch(err){addToast(errorMessage(err),'error');}finally{setActionSubmitting(false);}};
  const processReport=async code=>{try{const {data}=await api.post(`/reports/${code}/assign`,{});await afterAction(data.message);}catch(e){addToast(errorMessage(e),'error');}};
  const updateServiceStatus=async(code,status)=>{try{const {data}=await api.patch(`/reports/${code}/status`,{status});await afterAction(data.message);}catch(e){addToast(errorMessage(e),'error');}};
  const verifyReport=async code=>{try{const {data}=await api.post(`/reports/${code}/verify`);await afterAction(data.message);}catch(e){addToast(errorMessage(e),'error');}};
  const roleFlowText=role==='kelurahan'?'Kelurahan dapat input pengaduan wilayah sendiri, verifikasi awal, lalu mengajukannya ke Kecamatan.':role==='kecamatan'?'Kecamatan dapat memantau seluruh Kelurahan di wilayahnya, input laporan untuk Kelurahan terkait, serta melakukan validasi/cross-check sebelum diteruskan ke Kota.':'Kota dapat memonitor seluruh wilayah dan input laporan langsung sebagai laporan tingkat Kota untuk tindak lanjut/rujukan OPD.';

  return <div className="flex flex-col h-full animate-in fade-in">
    <ModalForm isOpen={showInputModal} onClose={closeManual} title="Input Pengaduan Warga">
      <form onSubmit={handleManualInputSubmit} onChange={()=>setManualDirty(true)} className="space-y-6">
        <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-950"><b>Alur wilayah:</b> laporan tetap terkait ke Kelurahan tujuan, tetapi tahap awal mengikuti level akun yang menginput. Kelurahan mulai dari verifikasi Kelurahan, Kecamatan mulai dari validasi Kecamatan, dan Kota masuk ke tahap Kota. Email warga dipakai untuk kode pelacakan.</div>
        <div className="grid gap-5 sm:grid-cols-2">
          <FieldGuide label="Sumber pengaduan" help="Kanal pertama warga menyampaikan pengaduan." required><select name="source" required className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900"><option value="datang_langsung">Datang langsung</option><option value="whatsapp">WhatsApp</option><option value="telepon">Telepon</option></select></FieldGuide>
          <FieldGuide label="Kelurahan tujuan awal" help="Daftar otomatis dibatasi sesuai hak akses akun." required><select name="region_id" required disabled={allowedRegionsLoading} className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 disabled:bg-slate-100"><option value="">{allowedRegionsLoading?'Memuat kelurahan...':'Pilih kelurahan'}</option>{allowedRegions.map(r=><option key={r.id} value={r.id}>{r.name}{r.parent?.name?` · Kec. ${r.parent.name}`:''}</option>)}</select></FieldGuide>
          <FieldGuide label="Kategori" required><select value={inputCategory} onChange={e=>{setInputCategory(e.target.value);if(e.target.value!=='ambulans')setInputType('darurat');}} className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900">{REPORT_CATEGORIES.map(([v,l])=><option key={v} value={v}>{l}</option>)}</select></FieldGuide>
          <FieldGuide label="Prioritas" help="Terpisah dari jenis ambulans." required><select value={inputPriority} onChange={e=>setInputPriority(e.target.value)} className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900">{REPORT_PRIORITIES.map(([v,l])=><option key={v} value={v}>{l}</option>)}</select></FieldGuide>
        </div>
        {inputCategory==='ambulans'&&<div><p className="mb-2 text-sm font-bold text-slate-800">Jenis layanan ambulans</p><div className="flex gap-2 rounded-xl bg-slate-100 p-1.5"><button type="button" onClick={()=>setInputType('darurat')} className={`flex-1 rounded-lg py-2 text-sm font-bold ${inputType==='darurat'?'bg-white text-red-700 shadow-sm':'text-slate-600'}`}>Darurat</button><button type="button" onClick={()=>setInputType('terjadwal')} className={`flex-1 rounded-lg py-2 text-sm font-bold ${inputType==='terjadwal'?'bg-white text-blue-700 shadow-sm':'text-slate-600'}`}>Terjadwal</button></div></div>}
        <div className="grid gap-5 sm:grid-cols-2">
          <FieldGuide label="Nama warga" required><input name="name" required minLength={3} className="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900"/></FieldGuide>
          <FieldGuide label="No. HP / WhatsApp" required><input name="phone" required type="tel" pattern="(?:\+62|62|0)8[1-9][0-9]{6,11}" placeholder="08xxxxxxxxxx" className="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900"/></FieldGuide>
          <FieldGuide label="Gmail / email warga" help="Kode SKB akan dikirim ke alamat ini." required><input name="email" required type="email" placeholder="nama@gmail.com" className="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900"/></FieldGuide>
          <FieldGuide label="NIK 16 digit" required><ProtectedNikInput name="nik" required pattern="[0-9]{16}" inputMode="numeric" maxLength={16} className="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-slate-900"/></FieldGuide>
          <FieldGuide label="RT"><input name="rt_number" maxLength={10} placeholder="01" className="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900"/></FieldGuide><FieldGuide label="RW"><input name="rw_number" maxLength={10} placeholder="01" className="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900"/></FieldGuide>
        </div>
        {inputCategory==='ambulans'&&inputType==='terjadwal'&&<div className="grid gap-5 sm:grid-cols-2"><FieldGuide label="Jadwal jemput" required><input name="scheduled_at" required type="datetime-local" className="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900"/></FieldGuide><FieldGuide label="Estimasi durasi" required><select name="service_duration_minutes" defaultValue="120" className="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900"><option value="60">1 jam</option><option value="120">2 jam</option><option value="180">3 jam</option><option value="240">4 jam</option></select></FieldGuide></div>}
        <FieldGuide label={inputCategory==='ambulans'?'Lokasi penjemputan':'Lokasi / alamat terkait'} required={inputCategory==='ambulans'}><textarea name="pickup_location" required={inputCategory==='ambulans'} minLength={inputCategory==='ambulans'?5:undefined} rows={3} className="w-full resize-none rounded-xl border border-slate-300 px-4 py-3 text-slate-900" placeholder={inputCategory==='ambulans'?'Wajib: alamat penjemputan dan patokan':'Opsional untuk kategori non-ambulans'}/></FieldGuide>
        <FieldGuide label={inputCategory==='ambulans'?'Kondisi medis / kebutuhan ambulans':'Isi pengaduan'} required><textarea name={inputCategory==='ambulans'?'medical_condition':'description'} required minLength={3} rows={4} className="w-full resize-none rounded-xl border border-slate-300 px-4 py-3 text-slate-900"/></FieldGuide>
        {inputCategory==='ambulans'&&<FieldGuide label="Tujuan ambulans"><input name="destination" className="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900"/></FieldGuide>}
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><div className="text-sm font-bold text-slate-900">Koordinat marker</div><div className="text-xs text-slate-500">Ambil dari perangkat bila laporan mencantumkan lokasi lapangan.</div></div><Button type="button" size="sm" variant="outline" onClick={()=>navigator.geolocation?navigator.geolocation.getCurrentPosition(p=>{setManualCoords({latitude:String(p.coords.latitude),longitude:String(p.coords.longitude)});addToast('Koordinat berhasil diambil.','success');},()=>addToast('Koordinat perangkat tidak dapat diakses.','error'),{enableHighAccuracy:true,timeout:10000}):addToast('Geolocation tidak didukung.','error')}><MapPin className="mr-2 h-4 w-4"/>Ambil Lokasi</Button></div>{manualCoords.latitude&&<div className="mt-2 font-mono text-xs text-slate-700">{manualCoords.latitude}, {manualCoords.longitude}</div>}</div>
        <div className="flex justify-end gap-3 border-t border-slate-100 pt-5"><Button type="button" variant="ghost" disabled={manualSubmitting} onClick={closeManual}>Batal</Button><Button type="submit" disabled={manualSubmitting||allowedRegionsLoading}>{manualSubmitting?'Menyimpan...':allowedRegionsLoading?'Memuat Wilayah...':'Simpan Pengaduan & Kirim Kode'}</Button></div>
      </form>
    </ModalForm>

    <ModalForm isOpen={Boolean(detail)} onClose={closeDetail} title="Detail Pengaduan">
      {detailLoading?<div className="py-12 text-center text-sm font-semibold text-slate-500">Memuat detail...</div>:detail&&<div className="grid gap-5 sm:grid-cols-2 text-sm">
        <div><p className="text-xs font-bold uppercase text-slate-500">Kode Pelacakan</p><p className="mt-1 font-mono font-black text-slate-950">{detail.code}</p></div><div><p className="text-xs font-bold uppercase text-slate-500">Alur</p><p className="mt-1 font-black text-blue-800">{workflowLabel(detail.workflow_status)}</p></div>
        <div><p className="text-xs font-bold uppercase text-slate-500">Warga</p><p className="mt-1 text-slate-900">{detail.name}</p></div><div><p className="text-xs font-bold uppercase text-slate-500">Gmail</p><p className="mt-1 break-all text-slate-900">{detail.email}</p></div>
        <div><p className="text-xs font-bold uppercase text-slate-500">Kelurahan</p><p className="mt-1 text-slate-900">{detail.kelurahan}</p></div><div><p className="text-xs font-bold uppercase text-slate-500">Kecamatan</p><p className="mt-1 text-slate-900">{detail.kecamatan}</p></div>
        <div><p className="text-xs font-bold uppercase text-slate-500">Kategori</p><p className="mt-1 text-slate-900">{categoryLabel(detail.category)}</p></div><div><p className="text-xs font-bold uppercase text-slate-500">Prioritas</p><p className="mt-1 font-bold text-slate-900">{priorityLabel(detail.priority)}</p></div>
        <div className="sm:col-span-2"><p className="text-xs font-bold uppercase text-slate-500">Lokasi</p><p className="mt-1 leading-6 text-slate-900">{detail.pickup_location||'-'}</p></div><div className="sm:col-span-2"><p className="text-xs font-bold uppercase text-slate-500">Isi laporan</p><p className="mt-1 whitespace-pre-line leading-6 text-slate-900">{detail.medical_condition||detail.description||'-'}</p></div>
        {detail.assigned_agency&&<div className="sm:col-span-2 rounded-xl bg-emerald-50 p-3 text-emerald-900"><b>OPD / Instansi:</b> {detail.assigned_agency}</div>}
        {detail.status_history?.length>0&&<div className="sm:col-span-2 border-t border-slate-200 pt-4"><p className="text-xs font-black uppercase text-slate-500">Riwayat proses</p><div className="mt-3 space-y-2">{detail.status_history.map((h,i)=><div key={`${h.created_at}-${i}`} className="rounded-xl bg-slate-50 p-3"><div className="text-xs font-bold text-slate-800">{h.from_status?workflowLabel(h.from_status):'Awal'} → <span className="text-blue-800">{workflowLabel(h.to_status)}</span></div><div className="mt-1 text-xs text-slate-500">{h.changed_by||'Sistem'} · {new Date(h.created_at).toLocaleString('id-ID')}</div>{h.reason&&<div className="mt-1 text-xs text-slate-700">{h.reason}</div>}</div>)}</div></div>}
      </div>}
    </ModalForm>

    <ModalForm isOpen={Boolean(decisionTarget)} onClose={()=>!actionSubmitting&&setDecisionTarget(null)} title={decisionTarget?.decision==='return'?'Kembalikan ke Kelurahan':'Tolak pada Validasi Kecamatan'}><form onSubmit={submitDecision} className="space-y-5"><div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Catatan wajib dan akan tampil pada riwayat agar Kelurahan mengetahui hasil cross-check.</div><FieldGuide label="Catatan validasi / alasan" required><textarea name="notes" required minLength={5} rows={4} className="w-full resize-none rounded-xl border border-slate-300 p-3 text-slate-900"/></FieldGuide><div className="flex justify-end gap-2"><Button type="button" variant="ghost" onClick={()=>setDecisionTarget(null)}>Batal</Button><Button type="submit" variant={decisionTarget?.decision==='reject'?'danger':'primary'} disabled={actionSubmitting}>{actionSubmitting?'Menyimpan...':'Simpan Keputusan'}</Button></div></form></ModalForm>
    <ModalForm isOpen={Boolean(opdTarget)} onClose={()=>!actionSubmitting&&setOpdTarget(null)} title="Teruskan ke OPD / Instansi Terkait"><form onSubmit={submitOpd} className="space-y-5"><FieldGuide label="Nama OPD / instansi" help="Contoh: Dinas Sosial, Dinas Kesehatan, BPJS, Disdukcapil, BPBD, Satpol PP." required><input name="agency" required minLength={2} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900"/></FieldGuide><FieldGuide label="Catatan rujukan"><textarea name="notes" rows={4} className="w-full resize-none rounded-xl border border-slate-300 p-3 text-slate-900"/></FieldGuide><div className="flex justify-end gap-2"><Button type="button" variant="ghost" onClick={()=>setOpdTarget(null)}>Batal</Button><Button type="submit" disabled={actionSubmitting}>{actionSubmitting?'Mengirim...':'Teruskan ke OPD'}</Button></div></form></ModalForm>

    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><h2 className="text-2xl font-black text-slate-950">Pelayanan & Pengaduan Warga</h2><p className="mt-1 max-w-3xl text-sm leading-6 text-slate-600">{roleFlowText}</p></div><Button onClick={openManual}><Plus className="mr-2 h-5 w-5"/>Input Pengaduan</Button></div>
    <div className="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-4"><select value={reportCategoryFilter} onChange={e=>{setReportCategoryFilter(e.target.value);setReportPage(1);}} className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900"><option value="">Semua kategori</option>{REPORT_CATEGORIES.map(([v,l])=><option key={v} value={v}>{l}</option>)}</select><select value={workflowFilter} onChange={e=>{setWorkflowFilter(e.target.value);setReportPage(1);}} className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900"><option value="">Semua tahapan</option>{Object.entries(WORKFLOW_LABELS).map(([v,l])=><option key={v} value={v}>{l}</option>)}</select><select value={reportStatusFilter} onChange={e=>{setReportStatusFilter(e.target.value);setReportPage(1);}} className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900"><option value="">Semua status operasional</option><option value="menunggu">Menunggu</option><option value="diproses">Diproses</option><option value="dijemput">Dijemput</option><option value="selesai">Selesai</option><option value="ditolak">Ditolak</option></select><div className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600"><span>{reportLoading?'Memuat...':`${reportMeta.total} pengaduan`}</span><span>20/halaman</span></div></div>
    <Card noPadding className="flex-1"><div className="overflow-x-auto"><table className="w-full min-w-[1250px] text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-600"><tr><th className="p-4 text-left">Kode</th><th className="p-4 text-left">Wilayah</th><th className="p-4 text-left">Warga</th><th className="p-4 text-left">Kategori / Prioritas</th><th className="p-4 text-left">Tahapan</th><th className="p-4 text-left">Status</th><th className="p-4 text-right">Aksi sesuai role</th></tr></thead><tbody>{!reportLoading&&reportRows.length===0&&<tr><td colSpan={7} className="p-12 text-center text-slate-500">Tidak ada pengaduan pada filter ini.</td></tr>}{reportRows.map(l=><tr key={l.id} className="border-t border-slate-100 align-top text-slate-800"><td className="p-4 font-mono font-black text-slate-950">{l.id}<div className="mt-1 text-[10px] font-sans uppercase text-slate-400">{l.sumber}</div></td><td className="p-4"><div className="font-bold text-slate-950">{l.kelurahan}</div><div className="text-xs text-slate-500">Kec. {l.kecamatan}</div></td><td className="p-4 font-semibold text-slate-900">{l.nama}</td><td className="p-4"><div className="font-bold">{categoryLabel(l.kategori)}</div><div className={`mt-1 text-xs font-black ${l.prioritas==='darurat'?'text-red-700':'text-slate-500'}`}>{priorityLabel(l.prioritas)}</div></td><td className="p-4"><span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">{workflowLabel(l.workflow)}</span></td><td className="p-4"><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{statusLabel(l.status)}</span></td><td className="p-4"><div className="flex flex-wrap justify-end gap-2"><Button size="sm" variant="outline" onClick={()=>openDetail(l.id)}><Eye className="mr-1 h-4 w-4"/>Detail</Button>
      {role==='kelurahan'&&['menunggu_kelurahan','perlu_perbaikan_kelurahan'].includes(l.workflow)&&<Button size="sm" onClick={()=>forwardKecamatan(l.id)}>Ajukan Kecamatan</Button>}
      {role==='kecamatan'&&l.workflow==='diajukan_kecamatan'&&<><Button size="sm" variant="success" onClick={()=>validateKecamatan(l.id)}>Validasi → Kota</Button><Button size="sm" variant="outline" onClick={()=>setDecisionTarget({code:l.id,decision:'return'})}>Kembalikan</Button><Button size="sm" variant="danger" onClick={()=>setDecisionTarget({code:l.id,decision:'reject'})}>Tolak</Button></>}
      {role==='kota'&&['diterima_kota','diteruskan_opd'].includes(l.workflow)&&<><Button size="sm" variant="outline" onClick={()=>setOpdTarget(l.id)}>Teruskan OPD</Button>{l.status==='menunggu'&&l.kategori==='ambulans'&&<Button size="sm" onClick={()=>requestConfirm('Tugaskan ambulans?','Penugasan dilakukan setelah laporan lolos validasi wilayah.','Tugaskan','Batal',()=>processReport(l.id),'success')}>Tugaskan Ambulans</Button>}{l.status==='menunggu'&&l.kategori!=='ambulans'&&<Button size="sm" onClick={()=>updateServiceStatus(l.id,'diproses')}>Mulai Proses</Button>}{l.status==='diproses'&&l.kategori==='ambulans'&&<Button size="sm" onClick={()=>updateServiceStatus(l.id,'dijemput')}>Dijemput</Button>}{['diproses','dijemput'].includes(l.status)&&<Button size="sm" variant="success" onClick={()=>updateServiceStatus(l.id,'selesai')}>Selesai</Button>}{l.status==='selesai'&&<Button size="sm" variant="outline" onClick={()=>verifyReport(l.id)}>Verifikasi Kota</Button>}</>}
    </div></td></tr>)}</tbody></table></div><div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/60 px-5 py-4"><span className="text-xs font-semibold text-slate-500">Halaman {reportMeta.current_page} dari {reportMeta.last_page}</span><div className="flex gap-2"><Button size="sm" variant="outline" disabled={reportLoading||reportMeta.current_page<=1} onClick={()=>setReportPage(p=>Math.max(1,p-1))}>Sebelumnya</Button><Button size="sm" variant="outline" disabled={reportLoading||reportMeta.current_page>=reportMeta.last_page} onClick={()=>setReportPage(p=>p+1)}>Berikutnya</Button></div></div></Card>
  </div>;
};

const ViewAmbulans = ({ db, refreshDashboard, role, addToast }) => {
  const [open,setOpen]=useState(false);
  const [editing,setEditing]=useState(null);
  const [detail,setDetail]=useState(null);
  const submit=async(e)=>{e.preventDefault();try{const f=Object.fromEntries(new FormData(e.currentTarget).entries());f.capacity=Number(f.capacity);if(editing){await api.patch(`/ambulances/${editing.db_id}`,f);addToast('Data ambulans diperbarui.','success');}else{await api.post('/ambulances',f);addToast('Unit ambulans ditambahkan.','success');}setOpen(false);setEditing(null);refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}};
  const openCreate=()=>{setEditing(null);setOpen(true);};
  const openEdit=(a)=>{setEditing(a);setOpen(true);};
  return <div>
    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6"><div><h2 className="text-2xl font-black text-slate-950">Manajemen Ambulans</h2><p className="text-sm text-slate-600">Lihat status unit dan perbarui data operasional ambulans dari kolom aksi.</p></div>{role==='kota'&&<Button onClick={openCreate}><Plus className="w-4 h-4 mr-2"/>Tambah Unit</Button>}</div>
    <Card noPadding><div className="overflow-x-auto"><table className="w-full min-w-[760px] text-sm"><thead><tr className="bg-slate-50 text-slate-700"><th className="p-4 text-left">Kode</th><th className="p-4 text-left">Nomor Polisi</th><th className="p-4 text-left">Kapasitas</th><th className="p-4 text-left">Status Saat Ini</th><th className="p-4 text-right">Aksi</th></tr></thead><tbody>{db.ambulans.map(a=><tr key={a.id} className="border-t border-slate-100 text-slate-800"><td className="p-4 font-bold text-slate-950">{a.id}</td><td className="p-4">{a.nopol}</td><td className="p-4">{a.kapasitas} orang</td><td className="p-4 uppercase"><span className={`px-3 py-1 rounded-full text-xs font-bold ${a.status==='tersedia'?'bg-emerald-50 text-emerald-700':a.status==='maintenance'?'bg-red-50 text-red-700':'bg-blue-50 text-blue-700'}`}>{statusLabel(a.status)}</span></td><td className="p-4"><div className="flex justify-end gap-2"><Button size="sm" variant="outline" onClick={()=>setDetail(a)}><Eye className="mr-1 h-4 w-4"/>Detail</Button>{role==='kota'&&<Button size="sm" variant="outline" onClick={()=>openEdit(a)}><Settings className="mr-1 h-4 w-4"/>Edit</Button>}</div></td></tr>)}</tbody></table></div></Card>
    <ModalForm isOpen={Boolean(detail)} onClose={()=>setDetail(null)} title="Detail Unit Ambulans">{detail&&<div className="grid gap-4 sm:grid-cols-2 text-sm"><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Kode unit</p><p className="mt-1 font-black text-slate-950">{detail.id}</p></div><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Nomor polisi</p><p className="mt-1 font-bold text-slate-950">{detail.nopol}</p></div><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Kapasitas</p><p className="mt-1 text-slate-900">{detail.kapasitas} orang</p></div><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Status</p><p className="mt-1 font-bold uppercase text-slate-900">{statusLabel(detail.status)}</p></div>{detail.notes&&<div className="sm:col-span-2"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Catatan</p><p className="mt-1 leading-6 text-slate-900">{detail.notes}</p></div>}</div>}</ModalForm>
    <ModalForm isOpen={open} onClose={()=>{setOpen(false);setEditing(null);}} title={editing?'Edit Unit Ambulans':'Tambah Unit Ambulans'}><form onSubmit={submit} className="space-y-5">
      {!editing&&<FieldGuide label="Kode unit" help="Kode unik internal, contoh KT-03." required><input name="code" required placeholder="KT-03" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>}
      <FieldGuide label="Nomor polisi" help="Nomor registrasi kendaraan yang ditampilkan di administrasi unit." required><input name="plate_number" required defaultValue={editing?.nopol||''} placeholder="Z 1234 AB" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <FieldGuide label="Kapasitas" help="Jumlah penumpang/pasien yang dapat dilayani sesuai kebijakan operasional." required><input name="capacity" required type="number" min="1" max="10" defaultValue={editing?.kapasitas||1} className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      {editing&&<FieldGuide label="Status unit" help="Pilih pemeliharaan hanya apabila unit tidak boleh menerima penugasan baru." required><select name="status" defaultValue={editing.status} className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"><option value="tersedia">Tersedia</option><option value="dipesan">Dipesan</option><option value="bertugas">Bertugas</option><option value="maintenance">Pemeliharaan</option></select></FieldGuide>}
      <FieldGuide label="Catatan" help="Opsional, misalnya kondisi kendaraan atau informasi servis."><textarea name="notes" rows={3} defaultValue={editing?.notes||''} className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <Button type="submit" className="w-full sm:w-auto">{editing?'Simpan Perubahan':'Simpan Unit'}</Button>
    </form></ModalForm>
  </div>;
};

const ViewKas = ({ db, refreshDashboard, role, addToast }) => {
  const [open,setOpen]=useState(false);
  const [detail,setDetail]=useState(null);
  const [detailLoading,setDetailLoading]=useState(false);
  const transactionDetailRequestRef=useRef(0);
  const [rejectTarget,setRejectTarget]=useState(null);
  const [rejectSubmitting,setRejectSubmitting]=useState(false);
  const [transactionSubmitting,setTransactionSubmitting]=useState(false);
  const [settingsSubmitting,setSettingsSubmitting]=useState(false);
  const [openSettings,setOpenSettings]=useState(false);
  const [setting,setSetting]=useState(null);
  const loadSetting=()=>api.get('/infaq/settings').then(r=>setSetting(r.data.setting)).catch(()=>{});
  const transactionRequestUuidRef=useRef(newRequestUuid());
  const [transactionRows,setTransactionRows]=useState([]);
  const [transactionPage,setTransactionPage]=useState(1);
  const [transactionMeta,setTransactionMeta]=useState({current_page:1,last_page:1,total:0});
  const [transactionStatusFilter,setTransactionStatusFilter]=useState('');
  const [transactionTypeFilter,setTransactionTypeFilter]=useState('');
  const [transactionLoading,setTransactionLoading]=useState(false);
  useEffect(()=>{
    if(role!=='kota')return;
    loadSetting();
    const onRefresh=()=>loadSetting();
    window.addEventListener('siagakarta:finance-changed',onRefresh);
    return()=>window.removeEventListener('siagakarta:finance-changed',onRefresh);
  },[role]);
  useEffect(()=>{
    if(role!=='kota')return;
    let cancelled=false;
    const load=async()=>{
      setTransactionLoading(true);
      try{const {data}=await api.get('/transactions',{params:{page:transactionPage,per_page:25,status:transactionStatusFilter||undefined,type:transactionTypeFilter||undefined}});if(cancelled)return;setTransactionRows((data.data||[]).map(mapTransactionRow));setTransactionMeta({current_page:data.current_page||1,last_page:data.last_page||1,total:data.total||0});}
      catch(err){if(!cancelled)addToast(errorMessage(err),'error');}
      finally{if(!cancelled)setTransactionLoading(false);}
    };
    load();
    const onRefresh=()=>load();window.addEventListener('siagakarta:finance-changed',onRefresh);
    return()=>{cancelled=true;window.removeEventListener('siagakarta:finance-changed',onRefresh);};
  },[role,transactionPage,transactionStatusFilter,transactionTypeFilter]);
  if(role!=='kota') return <Card><ShieldAlert className="w-8 h-8 mb-3"/><b>Modul keuangan hanya dapat diakses pengelola Kota.</b></Card>;
  const submit=async(e)=>{e.preventDefault();if(transactionSubmitting)return;setTransactionSubmitting(true);try{const f=Object.fromEntries(new FormData(e.currentTarget).entries());f.request_uuid=transactionRequestUuidRef.current;f.amount=Number(f.amount);await api.post('/transactions',f);addToast('Transaksi dicatat dan menunggu verifikasi.','success');setOpen(false);transactionRequestUuidRef.current=newRequestUuid();refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}finally{setTransactionSubmitting(false);}};
  const saveSetting=async(e)=>{e.preventDefault();if(settingsSubmitting)return;setSettingsSubmitting(true);try{const form=new FormData(e.currentTarget);form.set('is_active',e.currentTarget.is_active.checked?'1':'0');await api.post('/infaq/settings',form);addToast('Pengaturan pembayaran diperbarui.','success');setOpenSettings(false);loadSetting();}catch(err){addToast(errorMessage(err),'error');}finally{setSettingsSubmitting(false);}};
  const verify=async(id)=>{try{const {data}=await api.post(`/transactions/${id}/verify`);addToast(data.message,'success');refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}};
  const submitReject=async(e)=>{e.preventDefault();if(!rejectTarget||rejectSubmitting)return;const reason=String(new FormData(e.currentTarget).get('reason')||'').trim();if(reason.length<5){addToast('Alasan penolakan minimal 5 karakter.','error');return;}setRejectSubmitting(true);try{const {data}=await api.post(`/transactions/${rejectTarget}/reject`,{reason});addToast(data.message,'success');setRejectTarget(null);refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}finally{setRejectSubmitting(false);}};
  const openTransactionDetail=async(id)=>{const requestId=++transactionDetailRequestRef.current;setDetailLoading(true);setDetail({});try{const {data}=await api.get(`/transactions/${id}`);if(transactionDetailRequestRef.current===requestId)setDetail(data.transaction);}catch(err){if(transactionDetailRequestRef.current===requestId){setDetail(null);addToast(errorMessage(err),'error');}}finally{if(transactionDetailRequestRef.current===requestId)setDetailLoading(false);}};
  const closeTransactionDetail=()=>{transactionDetailRequestRef.current++;setDetailLoading(false);setDetail(null);};
  const viewProof=async(id)=>{const w=window.open('','_blank');try{const res=await api.get(`/transactions/${id}/proof`,{responseType:'blob'});const url=URL.createObjectURL(res.data);if(w)w.location=url;setTimeout(()=>URL.revokeObjectURL(url),60000);}catch(err){if(w)w.close();addToast(errorMessage(err),'error');}};
  return <div>
    <div className="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 mb-6"><div><h2 className="text-2xl font-black text-slate-950">Kas, Infaq & Transaksi</h2><p className="text-sm text-slate-600">Jenis pemasukan/pengeluaran kini terlihat langsung di tabel. Setiap baris selalu memiliki tombol aksi.</p></div><div className="flex flex-col sm:flex-row gap-2"><Button variant="outline" onClick={()=>{loadSetting();setOpenSettings(true);}}><QrCode className="w-4 h-4 mr-2"/>Pengaturan Pembayaran</Button><Button onClick={()=>setOpen(true)}><Plus className="w-4 h-4 mr-2"/>Tambah Transaksi</Button></div></div>
    <div className="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-3"><select value={transactionTypeFilter} onChange={e=>{setTransactionTypeFilter(e.target.value);setTransactionPage(1);}} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800"><option value="">Semua jenis</option><option value="pemasukan">Pemasukan</option><option value="pengeluaran">Pengeluaran</option></select><select value={transactionStatusFilter} onChange={e=>{setTransactionStatusFilter(e.target.value);setTransactionPage(1);}} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800"><option value="">Semua status</option><option value="pending">Menunggu Verifikasi</option><option value="verified">Terverifikasi</option><option value="rejected">Ditolak</option></select><div className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600"><span>{transactionLoading?'Memuat data...':`${transactionMeta.total} transaksi`}</span><span>25 / halaman</span></div></div>
    <Card noPadding><div className="overflow-x-auto"><table className="w-full min-w-[1120px] text-sm"><thead><tr className="bg-slate-50 text-slate-700"><th className="p-4 text-left">Kode</th><th className="p-4 text-left">Tanggal</th><th className="p-4 text-left">Jenis</th><th className="p-4 text-left">Sumber</th><th className="p-4 text-left">Pembayar / Kategori</th><th className="p-4 text-left">Nominal</th><th className="p-4 text-left">Status</th><th className="p-4 text-right">Aksi</th></tr></thead><tbody>{!transactionLoading&&transactionRows.length===0&&<tr><td colSpan={8} className="p-10 text-center text-sm font-semibold text-slate-500">Tidak ada transaksi pada filter ini.</td></tr>}{transactionRows.map(t=><tr key={t.id} className="border-t border-slate-100 text-slate-800"><td className="p-4 font-mono text-slate-950">{t.id}</td><td className="p-4">{t.tgl}</td><td className="p-4"><span className={`rounded-full px-3 py-1 text-xs font-black uppercase ${t.tipe==='pemasukan'?'bg-emerald-50 text-emerald-700':'bg-red-50 text-red-700'}`}>{t.tipe}</span></td><td className="p-4"><span className="text-xs font-bold uppercase text-slate-700">{t.source==='public_infaq'?'Infaq Warga':'Internal'}</span></td><td className="p-4"><div className="font-medium text-slate-900">{t.payer_name||t.kategori}</div>{t.payer_phone_last4&&<div className="text-xs text-slate-500">HP ****{t.payer_phone_last4}</div>}</td><td className="p-4 font-bold text-slate-950">Rp{Number(t.nominal).toLocaleString('id-ID')}</td><td className="p-4"><span className={`px-2.5 py-1 rounded-full text-xs font-bold ${t.status==='verified'?'bg-emerald-50 text-emerald-700':t.status==='rejected'?'bg-red-50 text-red-700':'bg-amber-50 text-amber-700'}`}>{statusLabel(t.status)}</span></td><td className="p-4"><div className="flex justify-end gap-2"><Button size="sm" variant="outline" onClick={()=>openTransactionDetail(t.db_id)}><Eye className="w-4 h-4 mr-1"/>Detail</Button>{t.has_proof&&<Button size="sm" variant="outline" onClick={()=>viewProof(t.db_id)}>Bukti</Button>}{t.status==='pending'&&<><Button size="sm" variant="success" onClick={()=>verify(t.db_id)}>Verifikasi</Button><Button size="sm" variant="danger" onClick={()=>setRejectTarget(t.db_id)}>Tolak</Button></>}</div></td></tr>)}</tbody></table></div><div className="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div className="text-xs font-semibold text-slate-500">Halaman {transactionMeta.current_page} dari {transactionMeta.last_page}</div><div className="flex gap-2"><Button size="sm" variant="outline" disabled={transactionLoading||transactionMeta.current_page<=1} onClick={()=>setTransactionPage(p=>Math.max(1,p-1))}>Sebelumnya</Button><Button size="sm" variant="outline" disabled={transactionLoading||transactionMeta.current_page>=transactionMeta.last_page} onClick={()=>setTransactionPage(p=>p+1)}>Berikutnya</Button></div></div></Card>
    <ModalForm isOpen={Boolean(detail)} onClose={closeTransactionDetail} title="Detail Transaksi">{detailLoading?<div className="py-12 text-center text-sm font-semibold text-slate-500">Memuat detail transaksi...</div>:detail&&<div className="grid gap-4 sm:grid-cols-2 text-sm"><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Kode</p><p className="mt-1 font-mono font-bold text-slate-950">{detail.code}</p></div><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Jenis</p><p className="mt-1 font-bold capitalize text-slate-950">{detail.type}</p></div><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Tanggal</p><p className="mt-1 text-slate-900">{detail.transaction_date}</p></div><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Status</p><p className="mt-1 font-bold uppercase text-slate-900">{statusLabel(detail.status)}</p></div><div className="sm:col-span-2"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Kategori / Pembayar</p><p className="mt-1 text-slate-900">{detail.payer_name||detail.category}</p></div><div className="sm:col-span-2"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Nominal</p><p className="mt-1 text-xl font-black text-slate-950">Rp{Number(detail.amount).toLocaleString('id-ID')}</p></div>{detail.description&&<div className="sm:col-span-2"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Keterangan</p><p className="mt-1 leading-6 text-slate-900">{detail.description}</p></div>}{detail.rejection_reason&&<div className="sm:col-span-2 rounded-xl border border-red-200 bg-red-50 p-3 text-red-800"><b>Alasan penolakan:</b> {detail.rejection_reason}</div>}{detail.status_history?.length>0&&<div className="sm:col-span-2 border-t border-slate-200 pt-4"><p className="text-xs font-black uppercase tracking-wider text-slate-500">Riwayat Status</p><div className="mt-3 space-y-2">{detail.status_history.map((h,i)=><div key={`${h.created_at}-${i}`} className="rounded-xl bg-slate-50 p-3"><div className="text-xs font-bold text-slate-800">{h.from_status?statusLabel(h.from_status):'Awal'} → <span className="uppercase text-emerald-700">{statusLabel(h.to_status)}</span></div><div className="mt-1 text-xs text-slate-500">{h.changed_by||'Sistem'} • {new Date(h.created_at).toLocaleString('id-ID')}</div>{h.reason&&<div className="mt-1 text-xs leading-5 text-slate-700">{h.reason}</div>}</div>)}</div></div>}</div>}</ModalForm>
    <ModalForm isOpen={open} onClose={()=>setOpen(false)} title="Tambah Transaksi"><form onSubmit={submit} className="space-y-5">
      <FieldGuide label="Jenis transaksi" help="Pemasukan menambah saldo kas. Pengeluaran mengurangi saldo setelah transaksi diverifikasi." required><select name="type" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"><option value="pemasukan">Pemasukan</option><option value="pengeluaran">Pengeluaran</option></select></FieldGuide>
      <FieldGuide label="Kategori" help="Contoh: donasi, operasional, BBM, perawatan ambulans, atau bantuan sosial." required><input name="category" required placeholder="Contoh: bbm" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <FieldGuide label="Nominal" help="Isi angka rupiah tanpa titik atau koma." required><input name="amount" required type="number" min="1" placeholder="Contoh: 250000" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <FieldGuide label="Tanggal transaksi" help="Tanggal transaksi benar-benar terjadi, bukan tanggal input data." required><input name="transaction_date" required type="date" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <FieldGuide label="Keterangan" help="Opsional. Tulis rincian yang membantu proses verifikasi."><textarea name="description" rows={3} placeholder="Rincian transaksi" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <Button disabled={transactionSubmitting} type="submit" className="w-full sm:w-auto">{transactionSubmitting?'Menyimpan...':'Simpan'}</Button>
    </form></ModalForm>
    <ModalForm isOpen={Boolean(rejectTarget)} onClose={()=>{if(!rejectSubmitting)setRejectTarget(null);}} title="Tolak Bukti Pembayaran"><form onSubmit={submitReject} className="space-y-5"><div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">Penolakan hanya dapat dilakukan saat transaksi masih pending. Alasan akan disimpan agar keputusan dapat diaudit.</div><FieldGuide label="Alasan penolakan" help="Jelaskan masalah pada bukti, nominal, atau identitas pembayaran. Minimal 5 karakter." required><textarea name="reason" required minLength={5} maxLength={500} rows={4} placeholder="Contoh: nominal pada bukti tidak sesuai dengan nominal yang diajukan." className="w-full resize-none rounded-xl border border-slate-300 p-3 text-slate-900"/></FieldGuide><div className="flex justify-end gap-2"><Button type="button" variant="ghost" disabled={rejectSubmitting} onClick={()=>setRejectTarget(null)}>Batal</Button><Button type="submit" variant="danger" disabled={rejectSubmitting}>{rejectSubmitting?'Menyimpan...':'Tolak Pembayaran'}</Button></div></form></ModalForm>
    <ModalForm isOpen={openSettings} onClose={()=>setOpenSettings(false)} title="Pengaturan Pembayaran"><form onSubmit={saveSetting} className="space-y-5">
      <FieldGuide label="Judul infaq" help="Judul yang tampil pada modal infaq warga." required><input name="title" required defaultValue={setting?.title||'Infaq Siaga Karta'} className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <FieldGuide label="Deskripsi" help="Jelaskan tujuan penggunaan dana secara singkat dan transparan."><textarea name="description" defaultValue={setting?.description||''} rows={3} className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <div className="grid gap-4 sm:grid-cols-2">
        <FieldGuide label="Nama bank" help="Contoh: BCA, BRI, Mandiri, BSI."><input name="bank_name" defaultValue={setting?.bank_name||''} placeholder="Contoh: BRI" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
        <FieldGuide label="Nomor rekening" help="Nomor rekening resmi penerima. Data ini tampil ke warga jika pembayaran aktif."><input name="account_number" inputMode="numeric" defaultValue={setting?.account_number||''} placeholder="Contoh: 1234567890" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
        <FieldGuide label="Nama pemilik rekening" help="Nama pemilik sesuai rekening bank." className="sm:col-span-2"><input name="account_name" defaultValue={setting?.account_name||''} placeholder="Nama pemilik rekening" className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      </div>
      <FieldGuide label="Instruksi pembayaran" help="Tuliskan langkah setelah scan QR, termasuk instruksi upload bukti jika diperlukan."><textarea name="payment_instructions" defaultValue={setting?.payment_instructions||''} rows={3} className="w-full p-3 border border-slate-300 rounded-xl text-slate-900"/></FieldGuide>
      <FieldGuide label="Gambar QR Code" help={setting?.has_qr?'QR sudah tersimpan. Upload file baru hanya jika ingin mengganti QR.':'Upload QR sebelum mengaktifkan pembayaran infaq publik.'}><input name="qr" type="file" accept="image/jpeg,image/png,image/webp" className="w-full p-3 border border-slate-300 rounded-xl text-slate-700"/></FieldGuide>
      {setting?.has_qr&&<label className="flex items-start gap-3 rounded-xl border border-slate-200 p-4"><input name="remove_qr" type="checkbox" value="1" className="mt-1 w-4 h-4"/><span><span className="block text-sm font-bold text-slate-900">Hapus QR lama</span><span className="mt-1 block text-xs text-slate-500">Centang hanya jika pembayaran akan memakai rekening tanpa QR, atau QR lama memang tidak berlaku.</span></span></label>}
      <label className="flex items-start gap-3 rounded-xl border border-slate-200 p-4"><input name="is_active" type="checkbox" defaultChecked={Boolean(setting?.is_active)} className="mt-1 w-4 h-4"/><span><span className="block text-sm font-bold text-slate-900">Aktifkan pembayaran infaq publik</span><span className="mt-1 block text-xs leading-5 text-slate-500">Jika aktif, warga dapat melihat QR dan/atau rekening resmi lalu mengirim bukti pembayaran.</span></span></label>
      <Button disabled={settingsSubmitting} type="submit" className="w-full sm:w-auto">{settingsSubmitting?'Menyimpan...':'Simpan Pengaturan'}</Button>
    </form></ModalForm>
  </div>;
};

const ViewUsers = ({ addToast }) => {
  const [users,setUsers]=useState([]);
  const [districts,setDistricts]=useState([]);
  const [villages,setVillages]=useState([]);
  const [page,setPage]=useState(1);
  const [meta,setMeta]=useState({current_page:1,last_page:1,total:0});
  const [loading,setLoading]=useState(false);
  const [regionLoading,setRegionLoading]=useState(false);
  const [submitting,setSubmitting]=useState(false);
  const [open,setOpen]=useState(false);
  const [editing,setEditing]=useState(null);
  const [formRole,setFormRole]=useState('kelurahan');
  const [formDistrictId,setFormDistrictId]=useState('');
  const [formRegionId,setFormRegionId]=useState('');

  const loadUsers=async()=>{
    setLoading(true);
    try{
      const {data}=await api.get('/users',{params:{page,per_page:20}});
      setUsers(data.data||[]);
      setMeta({current_page:data.current_page||1,last_page:data.last_page||1,total:data.total||0});
    }catch(e){addToast(errorMessage(e),'error');}
    finally{setLoading(false);}
  };
  const loadDistricts=async()=>{
    try{
      const {data}=await api.get('/regions',{params:{level:'kecamatan'}});
      setDistricts(data.regions||[]);
    }catch(e){addToast(errorMessage(e),'error');}
  };
  const loadVillages=async(districtId, preferredRegionId='')=>{
    if(!districtId){setVillages([]);setFormRegionId('');return;}
    setRegionLoading(true);
    try{
      const {data}=await api.get('/regions',{params:{level:'kelurahan',parent_id:districtId}});
      const rows=data.regions||[];
      setVillages(rows);
      setFormRegionId(preferredRegionId && rows.some(r=>String(r.id)===String(preferredRegionId)) ? String(preferredRegionId) : '');
    }catch(e){setVillages([]);setFormRegionId('');addToast(errorMessage(e),'error');}
    finally{setRegionLoading(false);}
  };

  useEffect(()=>{loadDistricts();},[]);
  useEffect(()=>{
    loadUsers();
    const onRefresh=()=>loadUsers();
    window.addEventListener('siagakarta:users-changed',onRefresh);
    return()=>window.removeEventListener('siagakarta:users-changed',onRefresh);
  },[page]);

  const openCreate=()=>{
    setEditing(null);setFormRole('kelurahan');setFormDistrictId('');setFormRegionId('');setVillages([]);setOpen(true);
  };
  const openEdit=async u=>{
    setEditing(u);setFormRole(u.role);setVillages([]);
    if(u.role==='kelurahan'){
      const districtId=String(u.region?.parent_id||'');
      setFormDistrictId(districtId);
      setFormRegionId('');
      if(districtId) await loadVillages(districtId,String(u.region_id||''));
    }else if(u.role==='kecamatan'){
      setFormDistrictId(String(u.region_id||''));setFormRegionId(String(u.region_id||''));
    }else{
      setFormDistrictId('');setFormRegionId(String(u.region_id||''));
    }
    setOpen(true);
  };
  const changeRole=e=>{
    const next=e.target.value;
    setFormRole(next);setFormDistrictId('');setFormRegionId('');setVillages([]);
  };
  const changeDistrict=async e=>{
    const id=e.target.value;
    setFormDistrictId(id);
    if(formRole==='kecamatan'){
      setFormRegionId(id);
      setVillages([]);
    }else{
      setFormRegionId('');
      await loadVillages(id);
    }
  };
  const submit=async e=>{
    e.preventDefault();if(submitting)return;
    setSubmitting(true);
    try{
      const payload=Object.fromEntries(new FormData(e.currentTarget).entries());
      if(editing){
        if(!payload.password)delete payload.password;
        await api.patch(`/users/${editing.id}`,payload);
        addToast('Data pengguna dan wilayah berhasil diperbarui.','success');
      }else{
        await api.post('/users',payload);
        addToast('Akun pengelola wilayah berhasil dibuat.','success');
      }
      setOpen(false);setEditing(null);setFormDistrictId('');setFormRegionId('');setVillages([]);await loadUsers();
    }catch(err){addToast(errorMessage(err),'error');}
    finally{setSubmitting(false);}
  };
  const toggleActive=async u=>{try{await api.patch(`/users/${u.id}`,{is_active:!u.is_active});addToast(`Akun ${u.is_active?'dinonaktifkan':'diaktifkan'}.`,'success');loadUsers();}catch(err){addToast(errorMessage(err),'error');}};

  return <div><div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="text-2xl font-black text-slate-950">Manajemen Pengguna & Wilayah</h2><p className="text-sm text-slate-600">Akun Kecamatan dan Kelurahan dibuat dengan pilihan wilayah bertingkat agar tidak salah mengikat hak akses.</p><p className="mt-1 text-xs font-semibold text-slate-500">{loading?'Memuat...':`${meta.total} akun terdaftar`}</p></div><Button onClick={openCreate}><Plus className="mr-2 h-4 w-4"/>Tambah Pengguna</Button></div>
    <Card noPadding><div className="overflow-x-auto"><table className="w-full min-w-[1050px] text-sm"><thead><tr className="bg-slate-50 text-slate-700"><th className="p-4 text-left">Nama</th><th className="p-4 text-left">Username</th><th className="p-4 text-left">Email</th><th className="p-4 text-left">Peran</th><th className="p-4 text-left">Wilayah</th><th className="p-4 text-left">Status</th><th className="p-4 text-right">Aksi</th></tr></thead><tbody>{!loading&&users.length===0&&<tr><td colSpan={7} className="p-10 text-center text-sm font-semibold text-slate-500">Belum ada akun pada halaman ini.</td></tr>}{users.map(u=><tr key={u.id} className="border-t border-slate-100 text-slate-800"><td className="p-4 font-semibold text-slate-950">{u.name}</td><td className="p-4">{u.username}</td><td className="p-4">{u.email}</td><td className="p-4 font-bold text-xs">{roleDisplay(u.role)}</td><td className="p-4"><div className="font-bold text-slate-900">{u.region?.name||'-'}</div><div className="text-xs text-slate-500">{u.region?.code||''}</div></td><td className="p-4"><span className={`rounded-full px-3 py-1 text-xs font-bold ${u.is_active?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-600'}`}>{u.is_active?'Aktif':'Nonaktif'}</span></td><td className="p-4"><div className="flex justify-end gap-2"><Button size="sm" variant="outline" onClick={()=>openEdit(u)}><Settings className="mr-1 h-4 w-4"/>Edit</Button>{u.role!=='kota'&&<Button size="sm" variant={u.is_active?'danger':'success'} onClick={()=>toggleActive(u)}>{u.is_active?'Nonaktifkan':'Aktifkan'}</Button>}</div></td></tr>)}</tbody></table></div><div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/60 px-5 py-4"><span className="text-xs font-semibold text-slate-500">Halaman {meta.current_page} dari {meta.last_page}</span><div className="flex gap-2"><Button size="sm" variant="outline" disabled={loading||meta.current_page<=1} onClick={()=>setPage(p=>Math.max(1,p-1))}>Sebelumnya</Button><Button size="sm" variant="outline" disabled={loading||meta.current_page>=meta.last_page} onClick={()=>setPage(p=>p+1)}>Berikutnya</Button></div></div></Card>
    <ModalForm isOpen={open} onClose={()=>{setOpen(false);setEditing(null);setFormDistrictId('');setFormRegionId('');setVillages([]);}} title={editing?'Edit Pengguna':'Tambah Pengguna'}><form onSubmit={submit} className="space-y-5"><FieldGuide label="Nama lengkap" required><input name="name" required defaultValue={editing?.name||''} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900"/></FieldGuide><FieldGuide label="Username" required><input name="username" required minLength={4} defaultValue={editing?.username||''} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900"/></FieldGuide><FieldGuide label="Email akun" required><input name="email" type="email" required defaultValue={editing?.email||''} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900"/></FieldGuide>
      <FieldGuide label="Tingkat Karang Taruna" help="Hak akses mengikuti tingkat akun dan wilayah yang dipilih." required><select name="role" value={formRole} onChange={changeRole} disabled={editing?.role==='kota'} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900">{editing?.role==='kota'&&<option value="kota">Kota</option>}{editing?.role!=='kota'&&<><option value="kecamatan">Kecamatan</option><option value="kelurahan">Kelurahan</option></>}</select></FieldGuide>
      {formRole==='kecamatan'&&<FieldGuide label="Kecamatan" help="Pilih satu kecamatan sebagai cakupan utama akun." required><select name="region_id" required value={formRegionId} onChange={changeDistrict} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900"><option value="">Pilih kecamatan</option>{districts.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select></FieldGuide>}
      {formRole==='kelurahan'&&<><FieldGuide label="Kecamatan" help="Pilih kecamatan terlebih dahulu untuk menyaring daftar kelurahan." required><select required value={formDistrictId} onChange={changeDistrict} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900"><option value="">Pilih kecamatan</option>{districts.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select></FieldGuide><FieldGuide label="Kelurahan" help={regionLoading?'Memuat kelurahan...':'Daftar hanya menampilkan kelurahan di kecamatan terpilih.'} required><select name="region_id" required value={formRegionId} onChange={e=>setFormRegionId(e.target.value)} disabled={!formDistrictId||regionLoading} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900"><option value="">{regionLoading?'Memuat...':'Pilih kelurahan'}</option>{villages.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select></FieldGuide></>}
      {formRole==='kota'&&<div className="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm font-semibold text-blue-800">Akun Kota utama tetap terikat pada Kota Bandung dan tidak dapat dipindahkan ke wilayah lain dari panel ini.</div>}
      <FieldGuide label={editing?'Password baru':'Password'} help={editing?'Kosongkan jika tidak diubah. Minimal 10 karakter.':'Minimal 10 karakter.'} required={!editing}><input name="password" type="password" minLength={10} required={!editing} className="w-full rounded-xl border border-slate-300 p-3 text-slate-900"/></FieldGuide><Button type="submit" disabled={submitting||regionLoading}>{submitting?'Menyimpan...':editing?'Simpan Perubahan':'Simpan Pengguna'}</Button></form></ModalForm>
  </div>;
};

const ViewLaporan = ({ addToast, role }) => {
  const download = async (url, filename) => { try { const res=await api.get(url,{responseType:'blob'}); const href=URL.createObjectURL(res.data); const a=document.createElement('a');a.href=href;a.download=filename;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(href),1000); } catch(err){addToast(errorMessage(err),'error');} };
  return <div className="max-w-4xl mx-auto animate-in fade-in">
    <Card className="mb-6 border-transparent shadow-lg bg-gradient-to-br from-[#0b3b78] to-[#07132f] text-white"><div className="flex items-center gap-4 sm:gap-5"><div className="w-12 h-12 sm:w-14 sm:h-14 bg-white/10 rounded-2xl flex items-center justify-center shrink-0"><FileSpreadsheet className="w-7 h-7 text-cyan-200"/></div><div><h2 className="text-xl sm:text-2xl font-black tracking-tight mb-1">Unduh Laporan Sistem</h2><p className="text-blue-100/90 text-sm font-medium">Pilih format laporan yang diperlukan. File akan diunduh langsung tanpa membuka halaman cetak.</p></div></div></Card>
    <div className="grid gap-4">
      {STAFF_ROLES.includes(role)&&<Card className="flex flex-col sm:flex-row sm:items-center justify-between gap-4"><div><div className="font-bold text-slate-900 text-lg">Laporan Pelayanan Warga</div><div className="text-sm text-slate-500 mt-1">Mencakup 10 kategori pengaduan, prioritas, wilayah, tahapan validasi Kelurahan → Kecamatan → Kota, status, dan OPD tujuan.</div></div><div className="grid grid-cols-2 sm:flex gap-2"><Button size="sm" variant="outline" onClick={()=>download('/exports/pelayanan.pdf','laporan-pelayanan-warga.pdf')}><Download className="w-4 h-4 mr-2"/>PDF</Button><Button size="sm" onClick={()=>download('/exports/pelayanan.csv','laporan-pelayanan-warga.csv')}><Download className="w-4 h-4 mr-2"/>Excel/CSV</Button></div></Card>}
      {role==='kota'&&<Card className="flex flex-col sm:flex-row sm:items-center justify-between gap-4"><div><div className="font-bold text-slate-900 text-lg flex items-center gap-2">Laporan Keuangan & Kas Infaq <ShieldCheck className="w-5 h-5 text-emerald-600"/></div><div className="text-sm text-slate-500 mt-1">Mencakup infaq warga, bukti yang sudah diverifikasi, dan transaksi internal.</div></div><div className="grid grid-cols-2 sm:flex gap-2"><Button size="sm" variant="outline" onClick={()=>download('/exports/keuangan.pdf','laporan-keuangan.pdf')}><Download className="w-4 h-4 mr-2"/>PDF</Button><Button size="sm" onClick={()=>download('/exports/keuangan.csv','laporan-keuangan.csv')}><Download className="w-4 h-4 mr-2"/>Excel/CSV</Button></div></Card>}
    </div>
  </div>;
};

export default function App() {
  const initialRoleFromPath = () => {
    const path = window.location.pathname;
    if (path.startsWith('/portal') || path.startsWith('/dashboard')) return 'login';
    return 'warga';
  };
  const [role, setRoleState] = useState(initialRoleFromPath);
  const [currentUser,setCurrentUser]=useState(null);
  const [db, setDb] = useState({laporan:[],ambulans:[],driver:[],transaksi:[],program:[]});
  const [publicStats,setPublicStats]=useState({ambulans_tersedia:0,layanan_selesai:0,program_aktif:0,bantuan_disalurkan:0});
  const [dashboardStats,setDashboardStats]=useState({saldo:0,pemasukan_bulan:0,pengeluaran_bulan:0,laporan_aktif:0,ambulans_tersedia:0,daily:[],activity:[]});
  const [toasts, setToasts] = useState([]);
  const [confirmModal, setConfirmModal] = useState({ isOpen: false });
  const dashboardInFlight=useRef(null);
  const dashboardRefreshQueued=useRef(false);
  const revisionRef=useRef(null);
  const notificationSignatureRef=useRef(null);
  const sessionWarningRef=useRef('');

  const setRole = (nextRole, options = {}) => {
    setRoleState(nextRole);
    const nextPath = nextRole === 'warga' ? '/' : nextRole === 'login' ? '/portal' : '/dashboard';
    if (window.location.pathname !== nextPath) {
      const method = options.replace ? 'replaceState' : 'pushState';
      window.history[method]({}, '', nextPath);
    }
  };
  const addToast = (msg, type = 'info') => {
    const id=Date.now()+Math.random();
    setToasts(p=>[...p,{id,msg,type}]);
    setTimeout(()=>setToasts(p=>p.filter(t=>t.id!==id)),4000);
  };
  const loadPublic=async()=>{
    try{const {data}=await api.get('/public/bootstrap');setPublicStats(data);setDb(p=>({...p,program:data.program||[]}));}catch{}
  };
  const refreshDashboard=async()=>{
    if(!getToken()) return;
    if(dashboardInFlight.current){
      dashboardRefreshQueued.current=true;
      return dashboardInFlight.current;
    }
    do {
      dashboardRefreshQueued.current=false;
      dashboardInFlight.current=(async()=>{
        try{
          const {data}=await api.get('/dashboard',{params:{_ts:Date.now()}});
          setDb(data.db);
          setDashboardStats(data.stats||{});
          window.dispatchEvent(new CustomEvent('siagakarta:dashboard-refreshed'));
        }catch(err){
          if(err?.response?.status!==401) addToast(errorMessage(err),'error');
        }
      })();
      await dashboardInFlight.current;
      dashboardInFlight.current=null;
    } while(dashboardRefreshQueued.current && getToken());
  };
  const restoreSession=async({replace=false}={})=>{
    if(!getToken()) return false;
    try{
      const {data}=await api.get('/auth/me');
      setCurrentUser(data.user);
      setAuthMeta({expires_at:data.expires_at,absolute_expires_at:data.absolute_expires_at});
      setRole(data.user.role,{replace});
      return true;
    }catch{
      setToken(null);
      setCurrentUser(null);
      return false;
    }
  };
  const refreshSession=async()=>{
    if(!getToken() || document.visibilityState!=='visible') return;
    try{
      const {data}=await api.post('/auth/refresh');
      setAuthMeta({expires_at:data.expires_at,absolute_expires_at:data.absolute_expires_at});
    }catch(err){ if(err?.response?.status!==401) console.warn('Session refresh failed'); }
  };
  const checkSync=async()=>{
    if(!getToken() || document.visibilityState!=='visible' || !STAFF_ROLES.includes(role)) return;
    try{
      const {data}=await api.get('/sync',{params:{_ts:Date.now()}});
      const nextRevisions=data.revisions||{};
      const previous=revisionRef.current;
      if(previous){
        const changedScopes=Object.keys(nextRevisions).filter(scope=>Number(nextRevisions[scope]||0)!==Number(previous[scope]||0));
        if(changedScopes.length){
          changedScopes.forEach(scope=>window.dispatchEvent(new CustomEvent(`siagakarta:${scope}-changed`)));
        }
      }
      revisionRef.current={...nextRevisions};
      const notificationSignature=JSON.stringify(data.notifications||{});
      if(notificationSignatureRef.current && notificationSignatureRef.current!==notificationSignature) window.dispatchEvent(new CustomEvent('siagakarta:notifications-changed'));
      notificationSignatureRef.current=notificationSignature;
    }catch(err){
      if(err?.response?.status!==401) console.warn('Dashboard sync failed:', errorMessage(err));
    }
  };

  useEffect(()=>{
    loadPublic();
    const boot=async()=>{
      if(getToken()) {
        const ok=await restoreSession({replace:window.location.pathname.startsWith('/dashboard')});
        if(!ok && window.location.pathname.startsWith('/dashboard')) setRole('login',{replace:true});
      } else {
        if(window.location.pathname.startsWith('/dashboard')) setRole('login',{replace:true});
        requestSharedSession();
      }
    };
    boot();

    const unsubscribe=subscribeAuth(async msg=>{
      if(msg.type==='SESSION_REQUEST') { broadcastSession(); return; }
      if((msg.type==='SESSION_RESPONSE'||msg.type==='TOKEN_SET') && msg.token) {
        setToken(msg.token,msg.meta,{broadcast:false});
        await restoreSession({replace:window.location.pathname.startsWith('/portal')||window.location.pathname.startsWith('/dashboard')});
      }
      if(msg.type==='LOGOUT') {
        setToken(null,null,{broadcast:false});
        setCurrentUser(null);
        if(window.location.pathname.startsWith('/dashboard')||window.location.pathname.startsWith('/portal')) setRole('login',{replace:true});
      }
    });
    const onUnauthorized=()=>{
      setToken(null);
      setCurrentUser(null);
      setRole(window.location.pathname.startsWith('/dashboard')?'login':'warga',{replace:true});
      addToast('Sesi berakhir. Silakan login kembali.','error');
    };
    const handlePopState=()=>{
      const path=window.location.pathname;
      if(path==='/'||path==='') setRoleState('warga');
      else if(path.startsWith('/portal')) { if(getToken()) restoreSession({replace:true}); else {setRoleState('login');requestSharedSession();} }
      else if(path.startsWith('/dashboard')) { if(getToken()) restoreSession({replace:true}); else {setRoleState('login');requestSharedSession();} }
      else setRole('warga',{replace:true});
    };
    window.addEventListener('siagakarta:unauthorized',onUnauthorized);
    window.addEventListener('popstate',handlePopState);
    return ()=>{unsubscribe();window.removeEventListener('siagakarta:unauthorized',onUnauthorized);window.removeEventListener('popstate',handlePopState);};
  },[]);

  useEffect(()=>{
    if(role!=='warga') return;
    const timer=setInterval(()=>{ if(document.visibilityState==='visible') loadPublic(); },15000);
    return()=>clearInterval(timer);
  },[role]);

  useEffect(()=>{
    if(!STAFF_ROLES.includes(role)) return;
    revisionRef.current=null;
    notificationSignatureRef.current=null;
    refreshDashboard();
    checkSync();
  },[role]);

  useEffect(()=>{
    const onVisible=()=>{ if(document.visibilityState==='visible'){refreshSession();checkSync();} };
    document.addEventListener('visibilitychange',onVisible);
    const refreshTimer=setInterval(refreshSession,10*60*1000);
    const syncTimer=setInterval(checkSync,20000);
    const warningTimer=setInterval(()=>{
      const meta=getAuthMeta();
      const hard=meta.absolute_expires_at ? new Date(meta.absolute_expires_at).getTime() : 0;
      if(!hard) return;
      const left=hard-Date.now();
      if(left>0 && left<=5*60*1000 && sessionWarningRef.current!==meta.absolute_expires_at){
        sessionWarningRef.current=meta.absolute_expires_at;
        addToast('Sesi maksimum akan berakhir kurang dari 5 menit lagi. Simpan pekerjaan Anda.','info');
      }
    },30000);
    return ()=>{document.removeEventListener('visibilitychange',onVisible);clearInterval(refreshTimer);clearInterval(syncTimer);clearInterval(warningTimer);};
  },[role]);

  const requestConfirm=(title,msg,confirmText,cancelText,onConfirm,type='danger')=>setConfirmModal({isOpen:true,title,msg,confirmText,cancelText,type,onConfirm:()=>{Promise.resolve(onConfirm()).finally(()=>setConfirmModal({isOpen:false}));},onCancel:()=>setConfirmModal({isOpen:false})});
  const updateDB=(table,newData)=>setDb(prev=>({...prev,[table]:newData}));

  return <>
    <div className="fixed top-4 left-4 right-4 sm:left-auto sm:top-6 sm:right-6 z-[9999] flex flex-col gap-3 pointer-events-none">{toasts.map(t=><div key={t.id} className="pointer-events-auto bg-[#111] text-white px-4 sm:px-5 py-3.5 rounded-xl w-full sm:w-auto shadow-2xl text-sm font-bold flex items-center gap-3">{t.type==='success'?<CheckCircle2 className="w-5 h-5 text-emerald-400"/>:<AlertCircle className={`w-5 h-5 ${t.type==='error'?'text-red-400':'text-blue-400'}`}/>} {t.msg}</div>)}</div>
    <ConfirmModal {...confirmModal}/>
    <DeveloperWatermark/>
    {role==='warga'&&<PublicView setRole={setRole} db={db} publicStats={publicStats} addToast={addToast} refreshPublic={loadPublic}/>}
    {role==='login'&&<Login setRole={setRole} addToast={addToast} onLogin={setCurrentUser} demo={publicStats.demo}/>}
    {STAFF_ROLES.includes(role)&&<DashboardLayout role={role} currentUser={currentUser} db={db} dashboardStats={dashboardStats} updateDB={updateDB} refreshDashboard={refreshDashboard} setRole={setRole} addToast={addToast} requestConfirm={requestConfirm}/>}
  </>;
}
