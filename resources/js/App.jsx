import React, { useState, useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  AlertCircle, CheckCircle2, ChevronRight, X, Menu, Search, 
  Home, Activity, Calendar, FileText, FileSpreadsheet, Users, 
  Settings, LogOut, Truck, Plus, Eye, Download, ShieldCheck, 
  User, ShieldAlert, Send, MessageCircle, QrCode, Upload, Clock, ReceiptText,
  Ambulance, ArrowRight, HeartHandshake
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip as RechartsTooltip, ResponsiveContainer } from 'recharts';
import api, { errorMessage, getToken, setToken } from './api';

const initialDB = {
  laporan: [
    { id: 'LPR-20260812-001', nama: 'Budi Santoso', kontak: '08123456789', lokasi: 'Jl. Merdeka No 12', kondisi: 'Kecelakaan motor', kategori: 'ambulans', jenis: 'darurat', status: 'menunggu', tgl: '2026-08-12T09:10:00', sumber: 'website' },
    { id: 'JDL-20260812-002', nama: 'Siti Aminah', kontak: '08198765432', lokasi: 'Perum Sari Indah', kondisi: 'Kontrol rutin', kategori: 'ambulans', jenis: 'terjadwal', status: 'selesai', tgl: '2026-08-10T08:00:00', sumber: 'website' }
  ],
  ambulans: [
    { id: 'KT-01', nopol: 'Z 1992 AB', kapasitas: 2, status: 'tersedia' },
    { id: 'KT-02', nopol: 'Z 8812 XY', kapasitas: 1, status: 'dipesan' }
  ],
  driver: [
    { id: 'D-01', nama: 'Agus Riyanto', status: 'aktif' },
    { id: 'D-02', nama: 'Hendra', status: 'aktif' }
  ],
  transaksi: [
    { id: 'TRX-001', tipe: 'pemasukan', kategori: 'donasi_program', nominal: 1500000, status: 'verified', tgl: '2026-08-01' },
    { id: 'TRX-002', tipe: 'pengeluaran', kategori: 'bbm', nominal: 250000, status: 'verified', tgl: '2026-08-05' }
  ],
  program: [
    { id: 'PRG-01', nama: 'Bantuan Warga Sakit Menahun', target: 5000000, terkumpul: 3500000, tersalurkan: 1000000, status: 'aktif', img: '/siaga-karta-community.png' },
    { id: 'PRG-02', nama: 'Santunan Yatim Piatu', target: 10000000, terkumpul: 8500000, tersalurkan: 8000000, status: 'aktif', img: '/hero-ambulance.png' }
  ]
};

const chartData = [
  { name: 'Sen', darurat: 4, sosial: 2 },
  { name: 'Sel', darurat: 3, sosial: 1 },
  { name: 'Rab', darurat: 2, sosial: 4 },
  { name: 'Kam', darurat: 6, sosial: 3 },
  { name: 'Jum', darurat: 5, sosial: 2 },
  { name: 'Sab', darurat: 8, sosial: 5 },
  { name: 'Min', darurat: 3, sosial: 2 },
];

const Button = ({ children, variant = 'primary', size = 'md', className = '', ...props }) => {
  const base = "inline-flex items-center justify-center font-bold rounded-xl transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100";
  const sizes = { sm: "px-3 py-1.5 text-xs", md: "px-4 py-2.5 text-sm", lg: "px-6 py-3.5 text-base" };
  const variants = {
    primary: "bg-[#111] text-white hover:bg-slate-800 shadow-md",
    danger: "bg-red-600 text-white hover:bg-red-700 shadow-md",
    success: "bg-emerald-600 text-white hover:bg-emerald-700 shadow-md",
    outline: "border-2 border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50",
    ghost: "text-slate-600 hover:bg-slate-100"
  };
  return <button className={`${base} ${sizes[size]} ${variants[variant]} ${className}`} {...props}>{children}</button>;
};

const Card = ({ children, className = '', noPadding = false }) => (
  <div className={`bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden ${noPadding ? '' : 'p-6'} ${className}`}>
    {children}
  </div>
);

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
        <div className="p-6 overflow-y-auto flex-1 hide-scroll bg-slate-50/50">
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
                  <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Online
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
              className="flex-1 px-4 py-2 bg-slate-100 rounded-xl outline-none focus:ring-2 focus:ring-[#07132f] text-sm"
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

const PublicView = ({ setRole, db, publicStats, addToast }) => {
  const isScrolled = useLandingScroll();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [formType, setFormType] = useState('darurat');
  const [reportCategory, setReportCategory] = useState('ambulans');
  const [showReport, setShowReport] = useState(false);
  const [showTrack, setShowTrack] = useState(false);
  const [showInfaq, setShowInfaq] = useState(false);
  const [showSuccess, setShowSuccess] = useState(false);
  const [trackCode, setTrackCode] = useState('');
  const [trackResult, setTrackResult] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [infaqInfo, setInfaqInfo] = useState({ active:false, title:'Infaq Siaga Karta', description:'', payment_instructions:'', has_qr:false, qr_url:null });
  const [infaqSubmitting, setInfaqSubmitting] = useState(false);
  const [infaqCode, setInfaqCode] = useState('');

  useEffect(() => {
    api.get('/public/infaq').then(({ data }) => setInfaqInfo(data.infaq || {})).catch(() => {});
  }, []);

  const openReport = (type) => {
    setReportCategory('ambulans');
    setFormType(type);
    setShowReport(true);
    setMobileMenuOpen(false);
  };

  const handleLaporSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const form = new FormData(e.currentTarget);
      form.set('category', reportCategory);
      form.set('type', reportCategory === 'ambulans' ? formType : 'darurat');
      const { data } = await api.post('/public/reports', form, { headers: { 'Content-Type': 'multipart/form-data' } });
      setTrackCode(data.tracking_code);
      setTrackResult(null);
      setShowReport(false);
      setShowSuccess(true);
      addToast(data.message, 'success');
    } catch (err) {
      addToast(errorMessage(err), 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleTrack = async () => {
    if (!trackCode.trim()) return;
    try {
      const { data } = await api.get(`/public/reports/${encodeURIComponent(trackCode.trim())}`);
      setTrackResult(data.report);
      addToast('Status laporan ditemukan.', 'success');
    } catch (err) {
      setTrackResult(null);
      addToast(errorMessage(err), 'error');
    }
  };

  const handleInfaqSubmit = async (e) => {
    e.preventDefault();
    setInfaqSubmitting(true);
    try {
      const form = new FormData(e.currentTarget);
      const { data } = await api.post('/public/infaq/payments', form, { headers: { 'Content-Type': 'multipart/form-data' } });
      setInfaqCode(data.payment_code);
      addToast(data.message, 'success');
      e.currentTarget.reset();
    } catch (err) {
      addToast(errorMessage(err), 'error');
    } finally {
      setInfaqSubmitting(false);
    }
  };

  const menu = [
    { label:'Beranda', href:'#beranda' },
    { label:'Layanan', href:'#layanan' },
    { label:'Program Sosial', href:'#program-sosial' },
    { label:'Tentang', href:'#tentang' },
  ];

  const serviceCards = [
    { icon: Ambulance, title:'Layanan Warga', desc:'Ajukan ambulans darurat atau sampaikan pengaduan BPJS dan bencana melalui satu alur laporan.', action:() => openReport('darurat'), actionLabel:'Buat Laporan', tone:'text-red-300 bg-red-400/10 border-red-300/20' },
    { icon: Calendar, title:'Jadwalkan Ambulans', desc:'Ajukan penjemputan terjadwal. Sistem memeriksa slot ambulans dan driver agar jadwal tidak saling bentrok.', action:() => openReport('terjadwal'), actionLabel:'Pilih Jadwal', tone:'text-cyan-300 bg-cyan-300/10 border-cyan-300/20' },
    { icon: Search, title:'Cek Status Layanan', desc:'Masukkan kode laporan untuk melihat perkembangan layanan, unit ambulans, dan driver yang sudah ditugaskan.', action:() => { setTrackResult(null); setShowTrack(true); }, actionLabel:'Lacak Laporan', tone:'text-blue-300 bg-blue-300/10 border-blue-300/20' },
    { icon: HeartHandshake, title:'Infaq Warga', desc:'Scan QR infaq resmi Siaga Karta, lakukan pembayaran, lalu unggah bukti pembayaran untuk diverifikasi admin.', action:() => { setInfaqCode(''); setShowInfaq(true); }, actionLabel:'Infaq via QR', tone:'text-emerald-300 bg-emerald-300/10 border-emerald-300/20' },
  ];

  return (
    <div className="min-h-[100dvh] bg-[#050b1c] font-sans text-white selection:bg-cyan-300/30 selection:text-white">
      <nav className={`fixed inset-x-0 top-0 z-50 transition-all duration-300 ${isScrolled ? 'border-b border-white/10 bg-[#061027]/90 shadow-2xl shadow-black/20 backdrop-blur-xl' : 'bg-transparent'}`}>
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-7 lg:px-8">
          <a href="#beranda" className="flex items-center gap-3" onClick={() => setMobileMenuOpen(false)}>
            <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-300/30 bg-cyan-300/10 font-black text-cyan-200">SK</div>
            <div>
              <div className="text-base font-black tracking-[0.08em] text-white sm:text-lg">SIAGA KARTA</div>
              <div className="hidden text-[10px] font-semibold uppercase tracking-[0.22em] text-cyan-300/80 sm:block">Layanan Warga Terpadu</div>
            </div>
          </a>

          <div className="hidden items-center gap-7 lg:flex">
            {menu.map((item) => <a key={item.label} href={item.href} className="group relative text-sm font-semibold text-slate-200/85 transition-colors hover:text-white">{item.label}<span className="absolute -bottom-2 left-0 h-px w-0 bg-cyan-300 transition-all duration-300 group-hover:w-full"/></a>)}
          </div>

          <div className="hidden items-center gap-3 md:flex">
            <button onClick={() => setShowTrack(true)} className="rounded-full border border-white/30 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-cyan-300 hover:bg-cyan-300/10">Cek Status</button>
            <button onClick={() => setRole('login')} className="rounded-full bg-white px-5 py-2.5 text-sm font-bold text-[#07132f] transition hover:bg-cyan-50">Portal Petugas</button>
          </div>

          <button type="button" aria-label="Buka menu" className="rounded-xl border border-white/10 p-2 text-white md:hidden" onClick={() => setMobileMenuOpen(v => !v)}>{mobileMenuOpen ? <X className="h-6 w-6"/> : <Menu className="h-6 w-6"/>}</button>
        </div>

        <AnimatePresence>
          {mobileMenuOpen && <motion.div initial={{ opacity:0, height:0 }} animate={{ opacity:1, height:'auto' }} exit={{ opacity:0, height:0 }} className="overflow-hidden border-t border-white/10 bg-[#061027]/95 backdrop-blur-xl md:hidden">
            <div className="space-y-1 px-5 py-5">
              {menu.map((item) => <a key={item.label} href={item.href} onClick={() => setMobileMenuOpen(false)} className="block rounded-xl px-3 py-3 text-sm font-semibold text-slate-200 hover:bg-white/5 hover:text-white">{item.label}</a>)}
              <button onClick={() => { setMobileMenuOpen(false); setShowTrack(true); }} className="block w-full rounded-xl px-3 py-3 text-left text-sm font-semibold text-cyan-200 hover:bg-white/5">Cek Status Laporan</button>
              <button onClick={() => { setMobileMenuOpen(false); setRole('login'); }} className="mt-2 w-full rounded-xl bg-white px-4 py-3 text-sm font-bold text-[#07132f]">Portal Petugas</button>
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
                SIAGA KARTA menghubungkan warga dengan layanan ambulans, pelacakan pelayanan, pengaduan BPJS dan bencana melalui alur laporan yang sama, program bantuan sosial, serta infaq yang transparan.
              </motion.p>
              <motion.div initial={{ opacity:0, y:18 }} animate={{ opacity:1, y:0 }} transition={{ duration:.65, delay:.3 }} className="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <button onClick={() => openReport('darurat')} className="inline-flex items-center justify-center gap-2 rounded-full bg-red-600 px-7 py-3.5 text-sm font-bold text-white shadow-xl shadow-red-950/25 transition hover:-translate-y-0.5 hover:bg-red-500"><AlertCircle className="h-4 w-4"/> Butuh Ambulans Darurat</button>
                <button onClick={() => openReport('terjadwal')} className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-7 py-3.5 text-sm font-bold text-[#07132f] transition hover:-translate-y-0.5 hover:bg-cyan-50"><Calendar className="h-4 w-4"/> Jadwalkan Ambulans</button>
                <button onClick={() => setShowTrack(true)} className="inline-flex items-center justify-center gap-2 rounded-full border border-white/40 bg-white/5 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition hover:border-cyan-200/70 hover:bg-cyan-200/10"><Search className="h-4 w-4"/> Cek Status</button>
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
              <p className="mt-5 text-sm leading-7 text-slate-400 sm:text-base">Semua tombol di bawah terhubung langsung ke backend Laravel SIAGA KARTA.</p>
            </motion.div>
            <div className="mt-14 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
              {serviceCards.map((item,index) => { const Icon=item.icon; return <motion.button key={item.title} type="button" onClick={item.action} initial={{ opacity:0, y:28 }} whileInView={{ opacity:1, y:0 }} viewport={{ once:true, margin:'-70px' }} transition={{ duration:.55, delay:index*.08 }} className="group rounded-3xl border border-white/10 bg-white/[0.045] p-6 text-left transition hover:-translate-y-1 hover:border-cyan-300/25 hover:bg-white/[0.07]">
                <div className={`mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border ${item.tone}`}><Icon className="h-6 w-6"/></div>
                <h3 className="text-xl font-bold text-white">{item.title}</h3>
                <p className="mt-4 min-h-[112px] text-sm font-light leading-7 text-slate-400">{item.desc}</p>
                <span className="mt-5 inline-flex items-center gap-2 text-sm font-bold text-cyan-300">{item.actionLabel}<ArrowRight className="h-4 w-4 transition group-hover:translate-x-1"/></span>
              </motion.button>; })}
            </div>
          </div>
        </section>

        <section id="program-sosial" className="bg-[#071128] py-24">
          <div className="mx-auto max-w-7xl px-5 sm:px-7 lg:px-8">
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
              <div className="max-w-2xl"><p className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">Program Bantuan</p><h2 className="mt-4 text-3xl font-light tracking-tight text-white md:text-5xl">Transparansi program sosial.</h2><p className="mt-5 text-sm leading-7 text-slate-400 sm:text-base">Warga dapat melihat target, dana terkumpul, dan bantuan yang telah disalurkan.</p></div>
              <button onClick={() => { setInfaqCode(''); setShowInfaq(true); }} className="inline-flex items-center justify-center gap-2 self-start rounded-full border border-cyan-300/30 bg-cyan-300/10 px-6 py-3 text-sm font-bold text-cyan-200 hover:bg-cyan-300/15"><QrCode className="h-4 w-4"/> Infaq via QR</button>
            </div>
            <div className="mt-12 grid gap-6 md:grid-cols-2">
              {db.program.length ? db.program.map((p,index) => {
                const pct = p.target > 0 ? Math.min(100, (p.terkumpul/p.target)*100) : 0;
                return <motion.article key={p.id} initial={{ opacity:0, y:24 }} whileInView={{ opacity:1, y:0 }} viewport={{ once:true }} transition={{ duration:.55, delay:index*.08 }} className="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.045]">
                  {p.img && <div className="h-56 overflow-hidden bg-slate-900"><img src={p.img} alt={p.nama} className="h-full w-full object-cover transition duration-500 hover:scale-105"/></div>}
                  <div className="p-6"><div className="flex items-start justify-between gap-4"><h3 className="text-xl font-bold text-white">{p.nama}</h3><span className="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300">AKTIF</span></div>
                    <div className="mt-6"><div className="flex justify-between gap-3 text-xs font-semibold text-slate-400"><span>Terkumpul Rp{Number(p.terkumpul).toLocaleString('id-ID')}</span><span className="text-cyan-300">{pct.toFixed(0)}%</span></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-white/10"><div className="h-full rounded-full bg-cyan-300" style={{ width:`${pct}%` }}/></div></div>
                    <div className="mt-5 flex items-center justify-between border-t border-white/10 pt-5"><div><div className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Telah Disalurkan</div><div className="mt-1 font-bold text-white">Rp{Number(p.tersalurkan).toLocaleString('id-ID')}</div></div><button onClick={() => { setInfaqCode(''); setShowInfaq(true); }} className="rounded-full border border-white/15 px-4 py-2 text-xs font-bold text-white hover:border-cyan-300/40 hover:text-cyan-200">Beri Infaq</button></div>
                  </div>
                </motion.article>;
              }) : <div className="md:col-span-2 rounded-3xl border border-white/10 bg-white/[0.04] p-10 text-center text-slate-400">Belum ada program sosial aktif.</div>}
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
              <p className="mt-7 max-w-xl text-base font-light leading-8 text-slate-200/85 md:text-lg">Permintaan warga masuk ke sistem, diproses petugas, lalu dapat dipantau melalui kode laporan. Untuk layanan terjadwal, backend memeriksa konflik waktu ambulans dan driver sebelum penugasan.</p>
              <div className="mt-10 grid max-w-xl gap-4 sm:grid-cols-2"><div className="rounded-2xl border border-white/10 bg-white/[0.06] p-5 backdrop-blur-md"><ShieldCheck className="h-6 w-6 text-cyan-300"/><p className="mt-4 font-bold text-white">Data lebih terlindungi</p><p className="mt-2 text-sm leading-6 text-slate-300/75">NIK divalidasi di backend dan data sensitif tidak dikirim kembali ke halaman publik.</p></div><div className="rounded-2xl border border-white/10 bg-white/[0.06] p-5 backdrop-blur-md"><Users className="h-6 w-6 text-cyan-300"/><p className="mt-4 font-bold text-white">Ramah untuk warga</p><p className="mt-2 text-sm leading-6 text-slate-300/75">Tampilan responsif untuk ponsel, tablet, laptop, dan desktop.</p></div></div>
            </motion.div>
          </div>
        </section>
      </main>

      <footer className="border-t border-white/10 bg-[#030816] py-8">
        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-5 text-center sm:px-7 md:flex-row md:text-left lg:px-8">
          <div className="flex items-center gap-3"><div className="flex h-9 w-9 items-center justify-center rounded-xl border border-cyan-300/25 bg-cyan-300/10 text-xs font-black text-cyan-200">SK</div><div><div className="text-sm font-bold text-white">SIAGA KARTA</div><div className="text-[10px] uppercase tracking-[0.18em] text-slate-500">Karang Taruna & Pelayanan Warga</div></div></div>
          <p className="text-xs font-light text-slate-500">© {new Date().getFullYear()} SIAGA KARTA. Sistem layanan warga terpadu.</p>
          <button onClick={() => setRole('login')} className="text-xs font-bold text-cyan-300 hover:text-cyan-200">Masuk Portal Petugas →</button>
        </div>
      </footer>

      <ModalForm isOpen={showReport} onClose={() => setShowReport(false)} title="Laporan & Pelayanan Warga">
        <form onSubmit={handleLaporSubmit} className="space-y-6">
          <input name="website" tabIndex={-1} autoComplete="off" className="hidden" aria-hidden="true"/>
          <div className="rounded-2xl border border-cyan-100 bg-cyan-50 p-4 text-sm text-cyan-950">
            Form yang sama menerima permintaan ambulans serta pengaduan BPJS dan bencana. Tidak ada menu warga tambahan.
          </div>
          <div>
            <label className="mb-2 block text-sm font-bold text-slate-700">Kategori Laporan *</label>
            <select value={reportCategory} onChange={(e)=>{setReportCategory(e.target.value); if(e.target.value!=='ambulans') setFormType('darurat');}} className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 font-semibold outline-none focus:ring-2 focus:ring-cyan-700">
              <option value="ambulans">Layanan Ambulans</option>
              <option value="bpjs">Pengaduan BPJS</option>
              <option value="bencana">Laporan Bencana</option>
            </select>
          </div>
          {reportCategory === 'ambulans' ? <>
            <div className={`flex items-start gap-3 rounded-2xl border p-4 text-sm ${formType === 'darurat' ? 'border-red-200 bg-red-50 text-red-800' : 'border-blue-200 bg-blue-50 text-blue-800'}`}>
              {formType === 'darurat' ? <AlertCircle className="mt-0.5 h-5 w-5 shrink-0"/> : <Clock className="mt-0.5 h-5 w-5 shrink-0"/>}
              <p>{formType === 'darurat' ? 'Gunakan layanan darurat untuk kondisi yang membutuhkan respons cepat. NIK wajib diketik manual.' : 'Pilih waktu penjemputan dan estimasi durasi. Sistem mencegah ambulans dan driver memiliki jadwal yang bertabrakan.'}</p>
            </div>
            <div className="flex gap-2 rounded-xl bg-slate-100 p-1.5"><button type="button" onClick={() => setFormType('darurat')} className={`flex-1 rounded-lg py-2 text-sm font-bold ${formType==='darurat'?'bg-white text-red-600 shadow-sm':'text-slate-500'}`}>Darurat</button><button type="button" onClick={() => setFormType('terjadwal')} className={`flex-1 rounded-lg py-2 text-sm font-bold ${formType==='terjadwal'?'bg-white text-blue-600 shadow-sm':'text-slate-500'}`}>Terjadwal</button></div>
          </> : <div className={`rounded-2xl border p-4 text-sm ${reportCategory==='bpjs'?'border-blue-200 bg-blue-50 text-blue-900':'border-amber-200 bg-amber-50 text-amber-900'}`}>{reportCategory==='bpjs'?'Jelaskan kendala BPJS secara rinci. Laporan masuk ke antrean Karang Taruna tanpa menugaskan ambulans.':'Jelaskan kejadian bencana, lokasi, kondisi warga, dan kebutuhan mendesak. Laporan masuk ke antrean Karang Taruna tanpa menugaskan ambulans.'}</div>}
          <div><h3 className="mb-4 border-b border-slate-200 pb-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">Identitas Pelapor</h3><div className="grid gap-4 sm:grid-cols-2"><div><label className="mb-1.5 block text-sm font-bold text-slate-700">Nama Lengkap *</label><input name="name" required minLength={3} className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-700"/></div><div><label className="mb-1.5 block text-sm font-bold text-slate-700">No. WhatsApp *</label><input name="phone" required type="tel" inputMode="tel" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-700"/></div><div className="sm:col-span-2"><label className="mb-1.5 block text-sm font-bold text-slate-700">NIK 16 Digit *</label><input name="nik" required pattern="[0-9]{16}" inputMode="numeric" maxLength={16} autoComplete="off" onPaste={(e)=>{e.preventDefault(); addToast('NIK harus diketik manual.','info');}} onDrop={(e)=>e.preventDefault()} placeholder="Ketik manual 16 digit NIK" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 font-mono outline-none focus:ring-2 focus:ring-cyan-700"/></div></div></div>
          {reportCategory === 'ambulans' && formType === 'terjadwal' && <div><label className="mb-1.5 block text-sm font-bold text-slate-700">Foto KTP *</label><input name="ktp" required type="file" accept="image/jpeg,image/png,image/webp" className="w-full rounded-xl border border-slate-200 bg-white p-3 text-sm"/></div>}
          <div><h3 className="mb-4 border-b border-slate-200 pb-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">Detail Laporan</h3><div className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2"><label className="mb-1.5 block text-sm font-bold text-slate-700">{reportCategory==='ambulans'?'Kondisi / Keperluan':'Isi Pengaduan / Kondisi'} *</label><textarea name="medical_condition" required minLength={3} rows={3} placeholder={reportCategory==='bpjs'?'Contoh: kepesertaan tidak aktif, rujukan, administrasi, atau kendala layanan BPJS...':reportCategory==='bencana'?'Contoh: banjir, kebakaran, longsor, warga terdampak, kebutuhan mendesak...':formType==='darurat'?'Kecelakaan, pingsan, kondisi pasien...':'Kontrol RS, pemeriksaan, kebutuhan pasien...'} className="w-full resize-none rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-700"/></div>
            {reportCategory==='ambulans' && formType==='terjadwal' && <div><label className="mb-1.5 block text-sm font-bold text-slate-700">Waktu Penjemputan *</label><input name="scheduled_at" required type="datetime-local" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-700"/></div>}
            {reportCategory==='ambulans' && formType==='terjadwal' && <div><label className="mb-1.5 block text-sm font-bold text-slate-700">Estimasi Durasi *</label><select name="service_duration_minutes" defaultValue="120" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-700"><option value="60">1 jam</option><option value="120">2 jam</option><option value="180">3 jam</option><option value="240">4 jam</option><option value="360">6 jam</option></select></div>}
            {reportCategory==='ambulans' && <div className="sm:col-span-2"><label className="mb-1.5 block text-sm font-bold text-slate-700">Tujuan</label><input name="destination" placeholder="Rumah sakit / tujuan (opsional)" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-700"/></div>}
            <div className="sm:col-span-2"><label className="mb-1.5 block text-sm font-bold text-slate-700">{reportCategory==='ambulans'?'Lokasi Penjemputan':'Lokasi / Alamat Terkait'} *</label><textarea name="pickup_location" required minLength={5} rows={3} placeholder={reportCategory==='bencana'?'Alamat/lokasi kejadian dan patokan...':reportCategory==='bpjs'?'Alamat pelapor atau fasilitas kesehatan terkait...':'Alamat lengkap, RT/RW, patokan...'} className="w-full resize-none rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-700"/></div>
          </div></div>
          <div className="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end"><Button type="button" variant="ghost" onClick={() => setShowReport(false)}>Batal</Button><Button disabled={submitting} type="submit" variant={reportCategory==='ambulans'&&formType==='darurat'?'danger':'primary'}>{submitting?'Mengirim...':reportCategory==='ambulans'?(formType==='darurat'?'Kirim Permintaan Darurat':'Ajukan Penjadwalan'):'Kirim Pengaduan'}</Button></div>
        </form>
      </ModalForm>

      <ModalForm isOpen={showTrack} onClose={() => setShowTrack(false)} title="Cek Status Layanan">
        <div className="space-y-5"><p className="text-sm leading-6 text-slate-600">Masukkan kode laporan yang diberikan setelah pengajuan.</p><div className="flex flex-col gap-3 sm:flex-row"><input value={trackCode} onChange={(e)=>setTrackCode(e.target.value.toUpperCase())} onKeyDown={(e)=>{if(e.key==='Enter') handleTrack();}} placeholder="LPR / JDL / BPJ / BNC - kode laporan" className="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 font-mono text-sm outline-none focus:ring-2 focus:ring-cyan-700"/><Button type="button" onClick={handleTrack}><Search className="mr-2 h-4 w-4"/>Cari</Button></div>{trackResult && <div className="rounded-2xl border border-cyan-100 bg-cyan-50 p-5"><div className="grid gap-4 text-sm sm:grid-cols-2"><div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Kode</div><div className="mt-1 font-mono font-bold text-slate-900">{trackResult.code}</div></div><div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Status</div><div className="mt-1 font-bold uppercase text-cyan-900">{trackResult.status}</div></div><div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Kategori</div><div className="mt-1 font-semibold uppercase text-slate-900">{trackResult.category || 'ambulans'}</div></div><div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Jenis</div><div className="mt-1 font-semibold text-slate-900">{trackResult.type}</div></div>{trackResult.scheduled_at && <div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Jadwal</div><div className="mt-1 font-semibold text-slate-900">{new Date(trackResult.scheduled_at).toLocaleString('id-ID')}</div></div>}{trackResult.ambulance && <div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Ambulans</div><div className="mt-1 font-semibold text-slate-900">{trackResult.ambulance}</div></div>}{trackResult.driver && <div><div className="text-xs font-bold uppercase tracking-wider text-slate-500">Driver</div><div className="mt-1 font-semibold text-slate-900">{trackResult.driver}</div></div>}</div></div>}</div>
      </ModalForm>

      <ModalForm isOpen={showSuccess} onClose={() => setShowSuccess(false)} title="Laporan Berhasil Dikirim">
        <div className="py-4 text-center"><div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><CheckCircle2 className="h-10 w-10"/></div><h3 className="mt-5 text-2xl font-black text-slate-900">Simpan kode laporan Anda</h3><p className="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-600">Gunakan kode ini untuk memantau status pelayanan. Jangan membagikannya kepada pihak yang tidak berkepentingan.</p><div className="mx-auto mt-6 max-w-md rounded-2xl border border-slate-200 bg-slate-50 p-5 font-mono text-lg font-black tracking-wider text-slate-900 sm:text-2xl">{trackCode}</div><div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row"><Button variant="outline" onClick={() => setShowSuccess(false)}>Tutup</Button><Button onClick={() => { setShowSuccess(false); setTrackResult(null); setShowTrack(true); }}>Cek Status Sekarang</Button></div></div>
      </ModalForm>

      <ModalForm isOpen={showInfaq} onClose={() => { setShowInfaq(false); setInfaqCode(''); }} title={infaqInfo.title || 'Infaq Siaga Karta'}>
        {!infaqInfo.active ? <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">Pembayaran infaq via QR belum diaktifkan oleh admin.</div> : <div className="space-y-6"><div className="grid gap-6 md:grid-cols-2"><div className="rounded-2xl border border-slate-200 bg-white p-4 text-center">{infaqInfo.has_qr ? <img src={infaqInfo.qr_url} alt="QR pembayaran infaq SIAGA KARTA" className="mx-auto aspect-square w-full max-w-[280px] rounded-xl object-contain"/> : <div className="flex aspect-square items-center justify-center text-slate-400"><QrCode className="h-20 w-20"/></div>}<p className="mt-3 text-xs text-slate-500">Scan QR dengan aplikasi pembayaran Anda.</p></div><div><p className="text-sm leading-7 text-slate-600">{infaqInfo.description}</p>{infaqInfo.payment_instructions && <div className="mt-4 whitespace-pre-line rounded-xl bg-cyan-50 p-4 text-sm text-cyan-950">{infaqInfo.payment_instructions}</div>}</div></div>{infaqCode ? <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center"><CheckCircle2 className="mx-auto h-8 w-8 text-emerald-600"/><div className="mt-2 font-bold text-emerald-900">Bukti pembayaran diterima</div><div className="mt-2 font-mono text-sm text-emerald-900">{infaqCode}</div><div className="mt-2 text-xs text-emerald-700">Pembayaran akan masuk ke kas setelah diverifikasi admin.</div></div> : <form onSubmit={handleInfaqSubmit} className="space-y-4"><input name="website" tabIndex={-1} autoComplete="off" className="hidden" aria-hidden="true"/><div className="grid gap-4 sm:grid-cols-2"><input name="payer_name" required minLength={3} placeholder="Nama lengkap" className="w-full rounded-xl border border-slate-200 p-3 outline-none focus:ring-2 focus:ring-cyan-700"/><input name="payer_phone" required type="tel" placeholder="No. WhatsApp" className="w-full rounded-xl border border-slate-200 p-3 outline-none focus:ring-2 focus:ring-cyan-700"/><input name="amount" required type="number" min="1000" placeholder="Nominal infaq" className="w-full rounded-xl border border-slate-200 p-3 outline-none focus:ring-2 focus:ring-cyan-700 sm:col-span-2"/></div><textarea name="description" rows={2} placeholder="Catatan opsional" className="w-full resize-none rounded-xl border border-slate-200 p-3 outline-none focus:ring-2 focus:ring-cyan-700"/><div><label className="mb-2 block text-sm font-bold text-slate-700">Upload bukti pembayaran *</label><input name="payment_proof" required type="file" accept="image/jpeg,image/png,image/webp" className="w-full rounded-xl border border-slate-200 bg-white p-3 text-sm"/></div><Button disabled={infaqSubmitting} type="submit" className="w-full"><Upload className="mr-2 h-4 w-4"/>{infaqSubmitting?'Mengirim bukti...':'Kirim Bukti Pembayaran'}</Button></form>}</div>}
      </ModalForm>

      <SiagaBot db={db}/>
    </div>
  );
};


const Login = ({ setRole, addToast, onLogin }) => {
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const handleLogin = async (e) => {
    e.preventDefault(); setLoading(true);
    try {
      const { data } = await api.post('/auth/login', { login: e.currentTarget.login.value, password: e.currentTarget.password.value });
      setToken(data.token); onLogin(data.user); setRole(data.user.role); addToast('Login berhasil.', 'success');
    } catch (err) { addToast(errorMessage(err), 'error'); } finally { setLoading(false); }
  };
  return (
  <div className="min-h-[100dvh] flex bg-[#FBFBFA] font-sans">
    <div className="hidden lg:block lg:w-1/2 relative overflow-hidden bg-[#022b20]">
      <img src="/hero-ambulance.png" className="absolute inset-0 w-full h-full object-cover mix-blend-overlay opacity-60" alt="Building" />
      <div className="absolute inset-0 bg-gradient-to-t from-[#022b20] via-transparent to-transparent"></div>
      <div className="absolute top-8 left-12 flex items-center gap-3"><div className="w-10 h-10 bg-white rounded-full flex items-center justify-center text-[#022b20] shadow-lg"><ShieldAlert className="w-5 h-5" /></div><span className="text-white font-bold text-xl tracking-tight">SIAGA KARTA</span></div>
      <div className="absolute bottom-16 left-12 right-12"><h1 className="text-5xl font-black text-white mb-6 leading-[1.1] tracking-tight">Kelola Pelayanan <br/>Kelurahan Terpadu.</h1><p className="text-white/80 text-lg max-w-md font-medium leading-relaxed">Sistem administrasi terpadu Karang Taruna untuk respon ambulans dan laporan warga yang cepat.</p></div>
    </div>
    <div className="w-full lg:w-1/2 flex flex-col justify-center items-center p-8 relative bg-white">
      <div className="absolute top-6 right-6"><button onClick={() => setRole('warga')} className="px-5 py-2.5 bg-slate-50 text-slate-600 text-sm font-bold rounded-full border border-slate-200">Kembali ke Portal Warga</button></div>
      <div className="w-full max-w-sm"><h2 className="text-3xl font-black text-slate-900 mb-2">Portal Petugas</h2><p className="text-slate-500 mb-10 text-sm font-medium">Masuk menggunakan akun resmi sistem.</p>
        <form className="space-y-5" onSubmit={handleLogin}>
          <div className="space-y-1.5"><label className="text-sm font-bold text-slate-700">Email atau Username</label><input name="login" required autoComplete="username" className="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#022b20] outline-none text-sm" /></div>
          <div className="space-y-1.5"><label className="text-sm font-bold text-slate-700">Password</label><div className="relative"><input name="password" required minLength={8} type={showPassword?'text':'password'} autoComplete="current-password" className="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#022b20] outline-none text-sm" /><button type="button" onClick={()=>setShowPassword(v=>!v)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"><Eye className="w-5 h-5"/></button></div></div>
          <button disabled={loading} type="submit" className="w-full py-4 bg-[#022b20] text-white rounded-xl font-bold disabled:opacity-50">{loading?'Memverifikasi...':'Masuk ke Dashboard'}</button>
        </form>
      </div>
    </div>
  </div>
  );
};

const DashboardLayout = ({ role, db, dashboardStats, updateDB, refreshDashboard, setRole, addToast, requestConfirm }) => {
  const [activeMenu, setActiveMenu] = useState('dashboard');
  const [mobileOpen, setMobileOpen] = useState(false);
  const [desktopCompact, setDesktopCompact] = useState(false);

  const sidebarNavs = [
    { id: 'dashboard', label: 'Beranda', icon: Home },
    { id: 'pelayanan', label: 'Pelayanan Warga', icon: Activity },
    { id: 'ambulans', label: 'Ambulans', icon: Truck },
    { id: 'laporan', label: 'Download Laporan', icon: Download },
  ];
  if(role === 'admin') sidebarNavs.splice(3,0,{ id: 'kas', label: 'Kas & Transaksi', icon: FileText });
  if(role === 'admin') sidebarNavs.push({ id: 'users', label: 'Manajemen User', icon: Users });

  const renderContent = () => {
    switch(activeMenu) {
      case 'dashboard': return <ViewDashboard db={db} role={role} stats={dashboardStats} />;
      case 'pelayanan': return <ViewPelayanan db={db} refreshDashboard={refreshDashboard} role={role} addToast={addToast} requestConfirm={requestConfirm} />;
      case 'ambulans': return <ViewAmbulans db={db} refreshDashboard={refreshDashboard} role={role} addToast={addToast} />;
      case 'kas': return <ViewKas db={db} refreshDashboard={refreshDashboard} role={role} addToast={addToast} />;
      case 'users': return <ViewUsers addToast={addToast} />;
      case 'laporan': return <ViewLaporan addToast={addToast} role={role} />;
      default: return null;
    }
  };
  const chooseMenu=(id)=>{setActiveMenu(id);setMobileOpen(false);};
  const sidebarContent = (mobile=false) => <>
    <div className="flex items-center justify-between px-5 mb-8">
      <div className={`flex items-center gap-3 overflow-hidden whitespace-nowrap ${!mobile && desktopCompact ? 'lg:w-0 lg:opacity-0' : ''}`}>
        <div className="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-white"><ShieldAlert className="w-6 h-6"/></div>
        <span className="font-black text-white">SIAGA KARTA</span>
      </div>
      {mobile ? <button onClick={()=>setMobileOpen(false)} className="p-2 text-white/80"><X className="w-5 h-5"/></button> : <button onClick={()=>setDesktopCompact(v=>!v)} className="hidden lg:flex w-8 h-8 items-center justify-center rounded-full bg-white/5 hover:bg-white/10 text-white"><ChevronRight className={`w-4 h-4 ${desktopCompact?'':'rotate-180'}`}/></button>}
    </div>
    <nav className="flex-1 flex flex-col px-3 gap-2">
      {sidebarNavs.map(nav => <button key={nav.id} onClick={()=>chooseMenu(nav.id)} className={`flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all ${activeMenu===nav.id?'bg-[#FBFBFA] text-[#022b20]':'text-white/70 hover:text-white hover:bg-white/5'}`} title={!mobile&&desktopCompact?nav.label:''}>
        <nav.icon className="w-5 h-5 shrink-0"/>{(mobile || !desktopCompact) && <span className="font-bold text-sm whitespace-nowrap">{nav.label}</span>}
      </button>)}
    </nav>
    <div className="p-3 mt-auto"><button onClick={()=>requestConfirm('Logout','Yakin ingin keluar dari sistem?','Logout','Batal',()=>{api.post('/auth/logout').catch(()=>{});setToken(null);setRole('warga');addToast('Berhasil logout','info');})} className="flex items-center gap-4 px-4 py-3.5 text-white/70 hover:text-white w-full rounded-xl hover:bg-white/5"><LogOut className="w-5 h-5 shrink-0"/>{(mobile || !desktopCompact) && <span className="font-bold text-sm">Logout Sistem</span>}</button></div>
  </>;

  return <div className="min-h-[100dvh] bg-[#011a13] lg:flex font-sans overflow-hidden">
    {mobileOpen && <button aria-label="Tutup menu" className="fixed inset-0 z-40 bg-black/50 lg:hidden" onClick={()=>setMobileOpen(false)}/>} 
    <aside className={`fixed inset-y-0 left-0 z-50 w-72 py-6 flex flex-col bg-[#011a13] transition-transform lg:hidden ${mobileOpen?'translate-x-0':'-translate-x-full'}`}>{sidebarContent(true)}</aside>
    <aside className={`hidden lg:flex ${desktopCompact?'w-20':'w-64'} py-8 flex-col shrink-0 transition-all duration-300`}>{sidebarContent(false)}</aside>
    <main className="min-w-0 flex-1 flex flex-col h-[100dvh] overflow-hidden bg-[#FBFBFA] lg:rounded-l-3xl lg:shadow-[-10px_0_30px_rgba(0,0,0,0.2)]">
      <header className="min-h-16 sm:min-h-20 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-3 border-b border-slate-100 bg-white/80 backdrop-blur-sm shrink-0">
        <div className="flex items-center gap-3 min-w-0"><button onClick={()=>setMobileOpen(true)} className="lg:hidden p-2 rounded-xl bg-slate-100 text-slate-700"><Menu className="w-5 h-5"/></button><div className="min-w-0"><h1 className="text-lg sm:text-2xl font-black text-slate-900 tracking-tight capitalize truncate">{activeMenu.replace('_',' ')}</h1><p className="hidden sm:block text-sm text-slate-500 font-medium">Sistem Administrasi {role==='admin'?'Kelurahan':'Karang Taruna'}</p></div></div>
        <div className="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-emerald-100 text-[#022b20] flex items-center justify-center font-bold border border-emerald-200 shrink-0">{role==='admin'?'AD':'KT'}</div>
      </header>
      <div className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 hide-scroll">{renderContent()}</div>
    </main>
  </div>;
};

const ViewDashboard = ({ db, role, stats }) => {
  return (
    <div className="animate-in fade-in flex flex-col h-full gap-6 max-w-7xl">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <Card className="col-span-1 md:col-span-2 bg-[#022b20] text-white border-none shadow-xl shadow-emerald-900/10">
          <h3 className="text-emerald-100 text-sm font-bold mb-1 uppercase tracking-widest">Saldo Kas Sosial</h3>
          <div className="text-4xl font-black mb-4 tracking-tight">{role==='admin'?`Rp ${Number(stats?.saldo||0).toLocaleString('id-ID')}`:'Khusus Admin'}</div>
          <div className="flex gap-6">
            <div><div className="text-xs text-emerald-300 font-bold uppercase tracking-wider mb-1">Masuk Bln Ini</div><div className="font-bold text-lg">{role==='admin'?`Rp${Number(stats?.pemasukan_bulan||0).toLocaleString('id-ID')}`:'-'}</div></div>
            <div><div className="text-xs text-red-300 font-bold uppercase tracking-wider mb-1">Keluar Bln Ini</div><div className="font-bold text-lg">{role==='admin'?`Rp${Number(stats?.pengeluaran_bulan||0).toLocaleString('id-ID')}`:'-'}</div></div>
          </div>
        </Card>
        <Card className="flex flex-col justify-center">
           <div className="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mb-4"><Activity className="w-6 h-6"/></div>
           <div className="text-3xl font-black text-slate-900">{stats?.laporan_aktif ?? db.laporan.filter(l=>l.status!=='selesai').length}</div>
           <div className="text-sm font-bold text-slate-500">Laporan Aktif Menunggu</div>
        </Card>
        <Card className="flex flex-col justify-center">
           <div className="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mb-4"><Truck className="w-6 h-6"/></div>
           <div className="text-3xl font-black text-slate-900">{stats?.ambulans_tersedia ?? db.ambulans.filter(a=>a.status==='tersedia').length}</div>
           <div className="text-sm font-bold text-slate-500">Unit Ambulans Standby</div>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 flex-1 min-h-[350px]">
        {/* Recharts Volume Laporan Area */}
        <Card className="lg:col-span-2 flex flex-col">
           <div className="flex justify-between items-center mb-6">
             <h3 className="font-bold text-slate-900">Volume Laporan Harian</h3>
             <select className="text-sm border-none bg-slate-50 p-2 rounded-lg outline-none font-bold text-slate-600"><option>7 Hari Terakhir</option></select>
           </div>
           <div className="flex-1 w-full h-full min-h-[250px]">
             <ResponsiveContainer width="100%" height="100%">
               <AreaChart data={chartData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                 <defs>
                   <linearGradient id="colorDarurat" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stopColor="#ef4444" stopOpacity={0.3}/><stop offset="95%" stopColor="#ef4444" stopOpacity={0}/></linearGradient>
                   <linearGradient id="colorSosial" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stopColor="#022b20" stopOpacity={0.3}/><stop offset="95%" stopColor="#022b20" stopOpacity={0}/></linearGradient>
                 </defs>
                 <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                 <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{fontSize: 12, fill: '#64748b', fontWeight: 'bold'}} />
                 <YAxis axisLine={false} tickLine={false} tick={{fontSize: 12, fill: '#64748b', fontWeight: 'bold'}} />
                 <RechartsTooltip contentStyle={{borderRadius: '16px', border: 'none', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)'}} />
                 <Area type="monotone" name="Layanan Ambulans" dataKey="darurat" stroke="#ef4444" strokeWidth={3} fillOpacity={1} fill="url(#colorDarurat)" />
                 <Area type="monotone" name="Pengaduan BPJS/Bencana" dataKey="sosial" stroke="#022b20" strokeWidth={3} fillOpacity={1} fill="url(#colorSosial)" />
               </AreaChart>
             </ResponsiveContainer>
           </div>
        </Card>
        
        {/* Recent Activity Feeds */}
        <Card className="flex flex-col bg-slate-50/50 border-none">
          <h3 className="font-bold text-slate-900 mb-6">Aktivitas Terkini</h3>
          <div className="flex-1 overflow-y-auto pr-2 space-y-6">
             {[1,2,3,4].map(i => (
               <div key={i} className="flex gap-4 relative before:absolute before:left-[11px] before:top-8 before:bottom-[-24px] before:w-[2px] before:bg-slate-200 last:before:hidden">
                 <div className="w-6 h-6 rounded-full bg-white border-2 border-slate-200 flex items-center justify-center flex-shrink-0 z-10">
                   <div className="w-2 h-2 rounded-full bg-emerald-500"></div>
                 </div>
                 <div>
                   <p className="text-sm font-medium text-slate-700">Laporan <span className="font-bold text-slate-900">LPR-00{i}</span> masuk sistem</p>
                   <p className="text-xs text-slate-400 mt-1 font-medium">{i * 10} menit lalu</p>
                 </div>
               </div>
             ))}
          </div>
        </Card>
      </div>
    </div>
  );
};

const ViewPelayanan = ({ db, refreshDashboard, role, addToast, requestConfirm }) => {
  const [showInputModal, setShowInputModal] = useState(false);
  const [inputType, setInputType] = useState('darurat');
  const [inputCategory, setInputCategory] = useState('ambulans');

  const handleManualInputSubmit = async (e) => {
    e.preventDefault();
    try { const form = new FormData(e.currentTarget); const payload=Object.fromEntries(form.entries()); payload.category=inputCategory; payload.type=inputCategory==='ambulans'?inputType:'darurat'; const {data}=await api.post('/reports/manual',payload); addToast(data.message,'success'); setShowInputModal(false); refreshDashboard(); }
    catch(err){addToast(errorMessage(err),'error');}
  };
  const processReport = async (code) => {
    try { const {data}=await api.post(`/reports/${code}/assign`,{}); addToast(data.message,'success'); refreshDashboard(); } catch(err){addToast(errorMessage(err),'error');}
  };
  const verifyReport = async(code)=>{ try{const {data}=await api.post(`/reports/${code}/verify`);addToast(data.message,'success');refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}};
  const updateServiceStatus=async(code,status)=>{try{const {data}=await api.patch(`/reports/${code}/status`,{status});addToast(data.message,'success');refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}};

  return (
    <div className="flex flex-col h-full animate-in fade-in">
      
      {/* Modal Input Manual (Karang Taruna Notification Form) */}
      <ModalForm isOpen={showInputModal} onClose={() => setShowInputModal(false)} title="Input Permohonan Warga">
         <form onSubmit={handleManualInputSubmit} className="space-y-6">
            <div className="p-4 bg-blue-50 text-blue-800 rounded-xl text-sm mb-2 border border-blue-100 flex items-start gap-3">
               <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
               <p>Gunakan form ini untuk mendata warga yang datang langsung ke posko atau melapor melalui WhatsApp/Telepon posko.</p>
            </div>

            <div className="space-y-1.5">
              <label className="text-sm font-bold text-slate-700">Sumber Informasi *</label>
              <select name="source" required className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#022b20] text-sm font-medium">
                <option value="datang_langsung">Datang Langsung (Ke Posko)</option>
                <option value="whatsapp">Via WhatsApp / Telepon</option>
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-sm font-bold text-slate-700">Kategori Laporan *</label>
              <select value={inputCategory} onChange={(e)=>{setInputCategory(e.target.value);if(e.target.value!=='ambulans')setInputType('darurat');}} className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#022b20] text-sm font-medium">
                <option value="ambulans">Layanan Ambulans</option><option value="bpjs">Pengaduan BPJS</option><option value="bencana">Laporan Bencana</option>
              </select>
            </div>
            {inputCategory==='ambulans' && <div className="flex gap-2 bg-slate-100 p-1.5 rounded-xl">
              <button type="button" onClick={() => setInputType('darurat')} className={`flex-1 py-2 text-sm font-bold rounded-lg transition-all ${inputType === 'darurat' ? 'bg-white shadow-sm text-red-600' : 'text-slate-500 hover:text-slate-700'}`}>Darurat (Cepat)</button>
              <button type="button" onClick={() => setInputType('terjadwal')} className={`flex-1 py-2 text-sm font-bold rounded-lg transition-all ${inputType === 'terjadwal' ? 'bg-white shadow-sm text-blue-600' : 'text-slate-500 hover:text-slate-700'}`}>Terjadwal</button>
            </div>}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div className="space-y-1.5"><label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Nama Pelapor *</label><input name="name" required className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#022b20] text-sm" /></div>
              <div className="space-y-1.5"><label className="text-xs font-bold text-slate-500 uppercase tracking-widest">No. Kontak *</label><input name="phone" required type="tel" className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#022b20] text-sm" /></div>
              <div className="space-y-1.5 md:col-span-2"><label className="text-xs font-bold text-slate-500 uppercase tracking-widest">NIK (16 Digit) *</label><input name="nik" required pattern="[0-9]{16}" inputMode="numeric" maxLength={16} onPaste={(e)=>{e.preventDefault();addToast('NIK harus diketik manual.','info');}} onDrop={(e)=>e.preventDefault()} className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#022b20] font-mono text-sm" placeholder="Ketik manual 16 digit" /></div>
            </div>
            
            {inputCategory==='ambulans' && inputType === 'terjadwal' && (
              <div className="grid grid-cols-1 gap-5 animate-in fade-in">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4"><div className="space-y-1.5"><label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Jadwal Jemput *</label><input name="scheduled_at" required type="datetime-local" className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#022b20] text-sm" /></div><div className="space-y-1.5"><label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Durasi *</label><select name="service_duration_minutes" defaultValue="120" className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl"><option value="60">1 jam</option><option value="120">2 jam</option><option value="180">3 jam</option><option value="240">4 jam</option><option value="360">6 jam</option></select></div></div>
              </div>
            )}

            <div className="space-y-1.5"><label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Lokasi / Alamat Terkait *</label><textarea name="pickup_location" required rows={3} className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#022b20] resize-none text-sm" /></div>
            <div className="space-y-1.5"><label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Keterangan / Isi Laporan *</label><textarea name="medical_condition" required rows={3} className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#022b20] resize-none text-sm" /></div>

            <div className="pt-6 flex gap-3 justify-end border-t border-slate-100">
              <Button type="button" variant="ghost" onClick={() => setShowInputModal(false)}>Batal</Button>
              <Button type="submit" variant="primary">Simpan ke Sistem</Button>
            </div>
         </form>
      </ModalForm>

      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
        <div>
          <h2 className="text-2xl font-black text-slate-900 tracking-tight">Daftar Pelayanan Warga</h2>
          <p className="text-sm text-slate-500 font-medium">Manajemen ambulans, pengaduan BPJS, dan laporan bencana dari warga.</p>
        </div>
        {(role === 'petugas' || role === 'admin') && (
          <Button onClick={() => setShowInputModal(true)} variant="primary" className="shadow-lg shadow-[#022b20]/20">
            <Plus className="w-5 h-5 mr-2"/> Tambah Permohonan Warga
          </Button>
        )}
      </div>

      <Card noPadding className="flex-1 flex flex-col min-h-[400px]">
         <div className="overflow-x-auto">
          <table className="w-full min-w-[1050px] text-sm text-left">
            <thead className="text-xs text-slate-500 uppercase tracking-wider bg-slate-50 border-b border-slate-100">
              <tr>
                <th className="px-6 py-5 font-bold">Kode Laporan</th>
                <th className="px-6 py-5 font-bold">Sumber</th>
                <th className="px-6 py-5 font-bold">Nama Pemohon</th>
                <th className="px-6 py-5 font-bold">Kategori</th>
                <th className="px-6 py-5 font-bold">Jadwal</th><th className="px-6 py-5 font-bold">Status Pelayanan</th>
                <th className="px-6 py-5 font-bold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {db.laporan.map((l) => (
                <tr key={l.id} className="hover:bg-slate-50/50 transition-colors group">
                  <td className="px-6 py-5 font-mono font-bold text-slate-900">{l.id}</td>
                  <td className="px-6 py-5 text-slate-500 uppercase text-xs font-bold">{l.sumber}</td>
                  <td className="px-6 py-5 text-slate-700 font-medium">{l.nama}</td>
                  <td className="px-6 py-5"><span className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${l.kategori==='bpjs'?'bg-blue-50 text-blue-700':l.kategori==='bencana'?'bg-orange-50 text-orange-700':'bg-emerald-50 text-emerald-700'}`}>{l.kategori||'ambulans'}</span></td>
                  <td className="px-6 py-5 text-xs text-slate-600"><div className="font-bold">{l.service_start_at ? new Date(l.service_start_at).toLocaleString('id-ID') : ((l.kategori||'ambulans')==='ambulans' && l.jenis==='darurat'?'Saat ditugaskan':'-')}</div>{l.service_end_at && <div className="text-slate-400 mt-1">s/d {new Date(l.service_end_at).toLocaleString('id-ID')}</div>} {l.ambulance && <div className="mt-1 text-emerald-700">{l.ambulance} • {l.driver}</div>}</td>
                  <td className="px-6 py-5">
                     <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border
                       ${l.status === 'menunggu' ? 'bg-amber-50 text-amber-700 border-amber-200' : 
                         l.status === 'selesai' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200'}`}>
                       {l.status === 'menunggu' && <span className="w-1.5 h-1.5 rounded-full bg-amber-500 mr-2"></span>}
                       {l.status === 'selesai' && <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></span>}
                       {l.status === 'diproses' && <span className="w-1.5 h-1.5 rounded-full bg-blue-500 mr-2"></span>}
                       {l.status.toUpperCase()}
                     </span>
                  </td>
                  <td className="px-6 py-5 text-right">
                    <div className="flex justify-end gap-2">
                      {l.status==='menunggu' && (l.kategori||'ambulans')==='ambulans' && <Button size="sm" onClick={()=>requestConfirm('Tugaskan Ambulans?','Sistem akan memilih ambulans dan driver yang tidak bentrok pada interval layanan ini.','Proses','Batal',()=>processReport(l.id),'success')}>Proses Penugasan</Button>}
                      {l.status==='menunggu' && (l.kategori||'ambulans')!=='ambulans' && <Button size="sm" onClick={()=>requestConfirm('Proses Pengaduan?','Laporan akan ditandai sedang ditangani Karang Taruna tanpa penugasan ambulans.','Proses','Batal',()=>updateServiceStatus(l.id,'diproses'),'success')}>Proses Pengaduan</Button>}
                      {l.status==='diproses' && (l.kategori||'ambulans')==='ambulans' && <Button size="sm" variant="outline" onClick={()=>requestConfirm('Mulai Penjemputan?','Status unit akan menjadi bertugas.','Mulai','Batal',()=>updateServiceStatus(l.id,'dijemput'),'success')}>Dijemput</Button>}
                      {l.status==='diproses' && (l.kategori||'ambulans')!=='ambulans' && <Button size="sm" variant="success" onClick={()=>requestConfirm('Selesaikan Pengaduan?','Pengaduan akan ditandai selesai dan dapat diverifikasi admin.','Selesai','Batal',()=>updateServiceStatus(l.id,'selesai'),'success')}>Selesaikan</Button>}
                      {l.status==='dijemput' && <Button size="sm" variant="success" onClick={()=>requestConfirm('Selesaikan Layanan?','Ambulans dan driver akan dilepas jika tidak ada layanan aktif lain.','Selesai','Batal',()=>updateServiceStatus(l.id,'selesai'),'success')}>Selesai</Button>}
                      {l.status==='selesai' && role==='admin' && <Button size="sm" variant="outline" onClick={()=>requestConfirm('Verifikasi Administrasi','Verifikasi laporan layanan selesai.','Verifikasi','Batal',()=>verifyReport(l.id),'success')}>Verifikasi Admin</Button>}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
         </div>
      </Card>
    </div>
  );
};

const ViewAmbulans = ({ db, refreshDashboard, role, addToast }) => {
  const [open,setOpen]=useState(false);
  const submit=async(e)=>{e.preventDefault();try{const f=Object.fromEntries(new FormData(e.currentTarget).entries());f.capacity=Number(f.capacity);await api.post('/ambulances',f);addToast('Unit ambulans ditambahkan.','success');setOpen(false);refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}};
  return <div><div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6"><div><h2 className="text-2xl font-black">Manajemen Ambulans</h2><p className="text-sm text-slate-500">Reservasi dicek berdasarkan interval waktu, bukan hanya status unit.</p></div>{role==='admin'&&<Button onClick={()=>setOpen(true)}><Plus className="w-4 h-4 mr-2"/>Tambah Unit</Button>}</div><Card noPadding><div className="overflow-x-auto"><table className="w-full min-w-[640px] text-sm"><thead><tr className="bg-slate-50"><th className="p-4 text-left">Kode</th><th className="p-4 text-left">Nopol</th><th className="p-4 text-left">Kapasitas</th><th className="p-4 text-left">Status Saat Ini</th></tr></thead><tbody>{db.ambulans.map(a=><tr key={a.id} className="border-t"><td className="p-4 font-bold">{a.id}</td><td className="p-4">{a.nopol}</td><td className="p-4">{a.kapasitas}</td><td className="p-4 uppercase"><span className={`px-3 py-1 rounded-full text-xs font-bold ${a.status==='tersedia'?'bg-emerald-50 text-emerald-700':a.status==='maintenance'?'bg-red-50 text-red-700':'bg-blue-50 text-blue-700'}`}>{a.status}</span></td></tr>)}</tbody></table></div></Card><ModalForm isOpen={open} onClose={()=>setOpen(false)} title="Tambah Unit Ambulans"><form onSubmit={submit} className="space-y-4"><input name="code" required placeholder="KT-03" className="w-full p-3 border rounded-xl"/><input name="plate_number" required placeholder="Nomor polisi" className="w-full p-3 border rounded-xl"/><input name="capacity" required type="number" min="1" max="10" placeholder="Kapasitas" className="w-full p-3 border rounded-xl"/><Button type="submit" className="w-full sm:w-auto">Simpan</Button></form></ModalForm></div>;
};

const ViewKas = ({ db, refreshDashboard, role, addToast }) => {
  const [open,setOpen]=useState(false);
  const [openSettings,setOpenSettings]=useState(false);
  const [setting,setSetting]=useState(null);
  const loadSetting=()=>api.get('/infaq/settings').then(r=>setSetting(r.data.setting)).catch(()=>{});
  useEffect(()=>{if(role==='admin')loadSetting();},[role]);
  if(role!=='admin') return <Card><ShieldAlert className="w-8 h-8 mb-3"/><b>Modul keuangan hanya dapat diakses Admin.</b></Card>;
  const submit=async(e)=>{e.preventDefault();try{const f=Object.fromEntries(new FormData(e.currentTarget).entries());f.amount=Number(f.amount);await api.post('/transactions',f);addToast('Transaksi dicatat dan menunggu verifikasi.','success');setOpen(false);refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}};
  const saveSetting=async(e)=>{e.preventDefault();try{const form=new FormData(e.currentTarget);form.set('is_active',e.currentTarget.is_active.checked?'1':'0');await api.post('/infaq/settings',form,{headers:{'Content-Type':'multipart/form-data'}});addToast('QR dan pengaturan infaq diperbarui.','success');setOpenSettings(false);loadSetting();}catch(err){addToast(errorMessage(err),'error');}};
  const verify=async(id)=>{try{const {data}=await api.post(`/transactions/${id}/verify`);addToast(data.message,'success');refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}};
  const reject=async(id)=>{const reason=window.prompt('Alasan penolakan bukti pembayaran:');if(!reason)return;try{const {data}=await api.post(`/transactions/${id}/reject`,{reason});addToast(data.message,'success');refreshDashboard();}catch(err){addToast(errorMessage(err),'error');}};
  const viewProof=async(id)=>{const w=window.open('','_blank');try{const res=await api.get(`/transactions/${id}/proof`,{responseType:'blob'});const url=URL.createObjectURL(res.data);if(w)w.location=url;setTimeout(()=>URL.revokeObjectURL(url),60000);}catch(err){if(w)w.close();addToast(errorMessage(err),'error');}};
  return <div>
    <div className="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 mb-6"><div><h2 className="text-2xl font-black">Kas, Infaq & Transaksi</h2><p className="text-sm text-slate-500">Infaq warga masuk pending sampai bukti diverifikasi Admin.</p></div><div className="flex flex-col sm:flex-row gap-2"><Button variant="outline" onClick={()=>setOpenSettings(true)}><QrCode className="w-4 h-4 mr-2"/>Pengaturan QR Infaq</Button><Button onClick={()=>setOpen(true)}><Plus className="w-4 h-4 mr-2"/>Tambah Transaksi</Button></div></div>
    <Card noPadding><div className="overflow-x-auto"><table className="w-full min-w-[980px] text-sm"><thead><tr className="bg-slate-50"><th className="p-4 text-left">Kode</th><th className="p-4 text-left">Tanggal</th><th className="p-4 text-left">Sumber</th><th className="p-4 text-left">Pembayar/Kategori</th><th className="p-4 text-left">Nominal</th><th className="p-4 text-left">Status</th><th className="p-4 text-right">Aksi</th></tr></thead><tbody>{db.transaksi.map(t=><tr key={t.id} className="border-t"><td className="p-4 font-mono">{t.id}</td><td className="p-4">{t.tgl}</td><td className="p-4"><span className="text-xs font-bold uppercase">{t.source==='public_infaq'?'Infaq Warga':'Internal'}</span></td><td className="p-4"><div className="font-medium">{t.payer_name||t.kategori}</div>{t.payer_phone_last4&&<div className="text-xs text-slate-400">WA ****{t.payer_phone_last4}</div>}</td><td className="p-4 font-bold">Rp{Number(t.nominal).toLocaleString('id-ID')}</td><td className="p-4"><span className={`px-2.5 py-1 rounded-full text-xs font-bold ${t.status==='verified'?'bg-emerald-50 text-emerald-700':t.status==='rejected'?'bg-red-50 text-red-700':'bg-amber-50 text-amber-700'}`}>{t.status}</span></td><td className="p-4"><div className="flex justify-end gap-2">{t.has_proof&&<Button size="sm" variant="outline" onClick={()=>viewProof(t.db_id)}><Eye className="w-4 h-4 mr-1"/>Bukti</Button>}{t.status==='pending'&&<><Button size="sm" variant="success" onClick={()=>verify(t.db_id)}>Verifikasi</Button><Button size="sm" variant="danger" onClick={()=>reject(t.db_id)}>Tolak</Button></>}</div></td></tr>)}</tbody></table></div></Card>
    <ModalForm isOpen={open} onClose={()=>setOpen(false)} title="Tambah Transaksi"><form onSubmit={submit} className="space-y-4"><select name="type" className="w-full p-3 border rounded-xl"><option value="pemasukan">Pemasukan</option><option value="pengeluaran">Pengeluaran</option></select><input name="category" required placeholder="Kategori" className="w-full p-3 border rounded-xl"/><input name="amount" required type="number" min="1" placeholder="Nominal" className="w-full p-3 border rounded-xl"/><input name="transaction_date" required type="date" className="w-full p-3 border rounded-xl"/><textarea name="description" placeholder="Keterangan" className="w-full p-3 border rounded-xl"/><Button type="submit" className="w-full sm:w-auto">Simpan</Button></form></ModalForm>
    <ModalForm isOpen={openSettings} onClose={()=>setOpenSettings(false)} title="Pengaturan QR Infaq"><form onSubmit={saveSetting} className="space-y-4"><input name="title" required defaultValue={setting?.title||'Infaq Siaga Karta'} placeholder="Judul" className="w-full p-3 border rounded-xl"/><textarea name="description" defaultValue={setting?.description||''} rows={3} placeholder="Deskripsi infaq" className="w-full p-3 border rounded-xl"/><textarea name="payment_instructions" defaultValue={setting?.payment_instructions||''} rows={3} placeholder="Instruksi pembayaran" className="w-full p-3 border rounded-xl"/><div><label className="block text-sm font-bold mb-2">Gambar QR Code</label><input name="qr" type="file" accept="image/jpeg,image/png,image/webp" className="w-full p-3 border rounded-xl"/><p className="text-xs text-slate-500 mt-2">{setting?.qr_path?'QR sudah tersimpan. Upload file baru untuk mengganti.':'Belum ada QR. Upload sebelum mengaktifkan infaq publik.'}</p></div><label className="flex items-center gap-2"><input name="is_active" type="checkbox" defaultChecked={Boolean(setting?.is_active)} className="w-4 h-4"/><span className="text-sm font-bold">Aktifkan pembayaran infaq publik</span></label><Button type="submit" className="w-full sm:w-auto">Simpan Pengaturan</Button></form></ModalForm>
  </div>;
};

const ViewUsers = ({ addToast }) => {
  const [users,setUsers]=useState([]); const [open,setOpen]=useState(false);
  const load=()=>api.get('/users').then(r=>setUsers(r.data)).catch(e=>addToast(errorMessage(e),'error'));
  useEffect(load,[]);
  const submit=async(e)=>{e.preventDefault();try{await api.post('/users',Object.fromEntries(new FormData(e.currentTarget).entries()));addToast('User berhasil dibuat.','success');setOpen(false);load();}catch(err){addToast(errorMessage(err),'error');}};
  return <div><div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6"><div><h2 className="text-2xl font-black">Manajemen User</h2><p className="text-sm text-slate-500">Akun Admin dan Petugas.</p></div><Button onClick={()=>setOpen(true)}><Plus className="w-4 h-4 mr-2"/>Tambah User</Button></div><Card noPadding><div className="overflow-x-auto"><table className="w-full min-w-[720px] text-sm"><thead><tr className="bg-slate-50"><th className="p-4 text-left">Nama</th><th className="p-4 text-left">Username</th><th className="p-4 text-left">Email</th><th className="p-4 text-left">Role</th><th className="p-4 text-left">Aktif</th></tr></thead><tbody>{users.map(u=><tr key={u.id} className="border-t"><td className="p-4">{u.name}</td><td className="p-4">{u.username}</td><td className="p-4">{u.email}</td><td className="p-4">{u.role}</td><td className="p-4">{u.is_active?'Ya':'Tidak'}</td></tr>)}</tbody></table></div></Card><ModalForm isOpen={open} onClose={()=>setOpen(false)} title="Tambah User"><form onSubmit={submit} className="space-y-4"><input name="name" required placeholder="Nama" className="w-full p-3 border rounded-xl"/><input name="username" required placeholder="Username" className="w-full p-3 border rounded-xl"/><input name="email" type="email" required placeholder="Email" className="w-full p-3 border rounded-xl"/><select name="role" className="w-full p-3 border rounded-xl"><option value="petugas">Petugas</option><option value="admin">Admin</option></select><input name="password" type="password" minLength={10} required placeholder="Password minimal 10 karakter" className="w-full p-3 border rounded-xl"/><Button type="submit" className="w-full sm:w-auto">Simpan</Button></form></ModalForm></div>;
};

const ViewLaporan = ({ addToast, role }) => {
  const download = async (url, filename) => { try { const res=await api.get(url,{responseType:'blob'}); const href=URL.createObjectURL(res.data); const a=document.createElement('a');a.href=href;a.download=filename;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(href),1000); } catch(err){addToast(errorMessage(err),'error');} };
  return <div className="max-w-4xl mx-auto animate-in fade-in">
    <Card className="mb-6 border-transparent shadow-lg bg-gradient-to-br from-[#022b20] to-[#011a13] text-white"><div className="flex items-center gap-4 sm:gap-5"><div className="w-12 h-12 sm:w-14 sm:h-14 bg-white/10 rounded-2xl flex items-center justify-center shrink-0"><FileSpreadsheet className="w-7 h-7 text-emerald-300"/></div><div><h2 className="text-xl sm:text-2xl font-black tracking-tight mb-1">Download Laporan Sistem</h2><p className="text-emerald-100/80 text-sm font-medium">Klik format yang dibutuhkan. File langsung terunduh tanpa membuka tab cetak.</p></div></div></Card>
    <div className="grid gap-4">
      <Card className="flex flex-col sm:flex-row sm:items-center justify-between gap-4"><div><div className="font-bold text-slate-900 text-lg">Laporan Pelayanan Warga</div><div className="text-sm text-slate-500 mt-1">Mencakup ambulans, pengaduan BPJS, laporan bencana, status, dan petugas layanan.</div></div><div className="grid grid-cols-2 sm:flex gap-2"><Button size="sm" variant="outline" onClick={()=>download('/exports/pelayanan.pdf','laporan-pelayanan-warga.pdf')}><Download className="w-4 h-4 mr-2"/>PDF</Button><Button size="sm" onClick={()=>download('/exports/pelayanan.csv','laporan-pelayanan-warga.csv')}><Download className="w-4 h-4 mr-2"/>Excel/CSV</Button></div></Card>
      {role==='admin'&&<Card className="flex flex-col sm:flex-row sm:items-center justify-between gap-4"><div><div className="font-bold text-slate-900 text-lg flex items-center gap-2">Laporan Keuangan & Kas Infaq <ShieldCheck className="w-5 h-5 text-emerald-600"/></div><div className="text-sm text-slate-500 mt-1">Mencakup infaq warga, bukti yang sudah diverifikasi, dan transaksi internal.</div></div><div className="grid grid-cols-2 sm:flex gap-2"><Button size="sm" variant="outline" onClick={()=>download('/exports/keuangan.pdf','laporan-keuangan.pdf')}><Download className="w-4 h-4 mr-2"/>PDF</Button><Button size="sm" onClick={()=>download('/exports/keuangan.csv','laporan-keuangan.csv')}><Download className="w-4 h-4 mr-2"/>Excel/CSV</Button></div></Card>}
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
  const setRole = (nextRole, options = {}) => {
    setRoleState(nextRole);
    const nextPath = nextRole === 'warga' ? '/' : nextRole === 'login' ? '/portal' : '/dashboard';
    if (window.location.pathname !== nextPath) {
      const method = options.replace ? 'replaceState' : 'pushState';
      window.history[method]({}, '', nextPath);
    }
  };
  const [currentUser,setCurrentUser]=useState(null);
  const [db, setDb] = useState({laporan:[],ambulans:[],driver:[],transaksi:[],program:[]});
  const [publicStats,setPublicStats]=useState({ambulans_tersedia:0,layanan_selesai:0,program_aktif:0,bantuan_disalurkan:0});
  const [dashboardStats,setDashboardStats]=useState({saldo:0,pemasukan_bulan:0,pengeluaran_bulan:0,laporan_aktif:0,ambulans_tersedia:0,daily:[]});
  const [toasts, setToasts] = useState([]);
  const [confirmModal, setConfirmModal] = useState({ isOpen: false });
  const addToast = (msg, type = 'info') => { const id=Date.now()+Math.random(); setToasts(p=>[...p,{id,msg,type}]); setTimeout(()=>setToasts(p=>p.filter(t=>t.id!==id)),4000); };
  const loadPublic=async()=>{try{const {data}=await api.get('/public/bootstrap');setPublicStats(data);setDb(p=>({...p,program:data.program||[]}));}catch{}};
  const refreshDashboard=async()=>{try{const {data}=await api.get('/dashboard');setDb(data.db);setDashboardStats(data.stats||{});}catch(err){if(err?.response?.status===401){setToken(null);setCurrentUser(null);setRole('warga');}else addToast(errorMessage(err),'error');}};
  useEffect(()=>{
    loadPublic();
    const restoreSession = () => {
      if (!getToken()) {
        if (window.location.pathname.startsWith('/dashboard')) setRole('login', { replace: true });
        return;
      }
      api.get('/auth/me').then(({data})=>{
        setCurrentUser(data.user);
        setRole(data.user.role, { replace: window.location.pathname.startsWith('/dashboard') });
        refreshDashboard();
      }).catch(()=>{
        setToken(null);
        setCurrentUser(null);
        if (window.location.pathname.startsWith('/dashboard')) setRole('login', { replace: true });
      });
    };
    restoreSession();
    const handlePopState = () => {
      const path = window.location.pathname;
      if (path === '/' || path === '') setRoleState('warga');
      else if (path.startsWith('/portal')) setRoleState('login');
      else if (path.startsWith('/dashboard')) {
        if (getToken()) restoreSession();
        else setRole('login', { replace: true });
      } else setRole('warga', { replace: true });
    };
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  },[]);
  useEffect(()=>{if(role==='admin'||role==='petugas') refreshDashboard();},[role]);
  const requestConfirm=(title,msg,confirmText,cancelText,onConfirm,type='danger')=>setConfirmModal({isOpen:true,title,msg,confirmText,cancelText,type,onConfirm:()=>{Promise.resolve(onConfirm()).finally(()=>setConfirmModal({isOpen:false}));},onCancel:()=>setConfirmModal({isOpen:false})});
  const updateDB=(table,newData)=>setDb(prev=>({...prev,[table]:newData}));
  return <>
    <div className="fixed top-4 left-4 right-4 sm:left-auto sm:top-6 sm:right-6 z-[9999] flex flex-col gap-3 pointer-events-none">{toasts.map(t=><div key={t.id} className="pointer-events-auto bg-[#111] text-white px-4 sm:px-5 py-3.5 rounded-xl w-full sm:w-auto shadow-2xl text-sm font-bold flex items-center gap-3">{t.type==='success'?<CheckCircle2 className="w-5 h-5 text-emerald-400"/>:<AlertCircle className={`w-5 h-5 ${t.type==='error'?'text-red-400':'text-blue-400'}`}/>} {t.msg}</div>)}</div>
    <ConfirmModal {...confirmModal}/>
    {role==='warga'&&<PublicView setRole={setRole} db={db} publicStats={publicStats} addToast={addToast}/>} 
    {role==='login'&&<Login setRole={setRole} addToast={addToast} onLogin={setCurrentUser}/>} 
    {(role==='admin'||role==='petugas')&&<DashboardLayout role={role} db={db} dashboardStats={dashboardStats} updateDB={updateDB} refreshDashboard={refreshDashboard} setRole={setRole} addToast={addToast} requestConfirm={requestConfirm}/>} 
  </>;
}
