import { type ReactNode, useEffect, useState } from "react";
import { Link, usePage, router } from "@inertiajs/react";
import { logout } from "@/routes";
import ganhos from "@/routes/ganhos";
import despesasFixas from "@/routes/despesas-fixas";
import despesasVariaveis from "@/routes/despesas-variaveis";
import dividas from "@/routes/dividas";
import investimentos from "@/routes/investimentos";
import metas from "@/routes/metas";
import fontesRendaRoutes from "@/routes/fontes-renda";
import categoriasRoutes from "@/routes/categorias";
import formasPagamentoRoutes from "@/routes/formas-pagamento";
import { IconPreview } from "@/components/icon-picker";
import { Combobox, ComboboxContent, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } from "@/components/ui/combobox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Spinner } from "@/components/ui/spinner";
import DespesaVariavelModal, { type DespesaFormData } from "@/components/despesa-variavel-modal";
import GanhoModal, { type GanhoFormData } from "@/components/ganho-modal";
import DespesaFixaModal, { type DespesaFixaFormData } from "@/components/despesa-fixa-modal";
import DividaModal, { type DividaFormData } from "@/components/divida-modal";
import InvestimentoModal, { type InvestimentoFormData, type MetaInvestFormData } from "@/components/investimento-modal";
import MetaModal, { type MetaFormData } from "@/components/meta-modal";
import ConfigModal, { type ConfigFormData } from "@/components/config-modal";

/* ── TYPES ─────────────────────────────────────────────────────────────────── */

interface BalancoMensal { mes: string; receita: number; despesa: number }
interface Ganho { id: number; descricao: string; fonte: string; data: string; valor: number; balanco: string }
interface DespesaFixa { id: number; descricao: string; categoria: string; valor: number; vencimento: string; status: string; dataPgto: string; forma: string; balanco: string }
interface DespesaVariavel { id: number; descricao: string; categoria: string; valor: number; data: string; balanco: string; forma: string }
interface Divida { id: number; descricao: string; destino: string; valor: number; vencimento: string; status: string; balanco: string }
interface Investimento { id: number; produto: string; empresa: string; valor: number; quantidade: number; valorTotal: number; tipoAtivo: string; provento: number; frequencia: string; data: string; balanco: string }
interface Meta { id: number; nome: string; icone: string | null; percent: number; valor: number; investido: number; faltante: number }
interface FonteRenda { id: number; nome: string; icone: string | null; percent: number; metaAnual: number; receitaAnual: number }
interface Categoria { id: number; nome: string; icone: string | null; pct: number; lim: number | null; desp: number }
interface FormaPagamento { id: number; nome: string; icone: string | null; pct: number; lim: number; desp: number }

interface PageProps {
    auth: { user: { name: string } };
    ano: number;
    balancoMensal: BalancoMensal[];
    ganhos: Ganho[];
    fixas: DespesaFixa[];
    variaveis: DespesaVariavel[];
    dividas: Divida[];
    investimentos: Investimento[];
    metas: Meta[];
    fontes: FonteRenda[];
    categorias: Categoria[];
    formas: FormaPagamento[];
    configFontes: string[];
    configCategorias: string[];
    configFormas: string[];
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
interface Column<T = any> {
    key: string;
    label: string;
    align?: "right";
    render?: (row: T) => ReactNode;
}
interface FooterItem { label: string; value: string | number }

/* ── HELPERS ───────────────────────────────────────────────────────────────── */

const MONTHS = ["Jan","Fev","Mar","Abr","Mai","Jun","Jul","Ago","Set","Out","Nov","Dez"] as const;
const FULL = ["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"] as const;
const fmt = (v: number): string => v.toLocaleString("pt-BR",{style:"currency",currency:"BRL"});
const toFull = (a: string): string => FULL[MONTHS.indexOf(a as typeof MONTHS[number])] || a;
const mmYYYYtoFull = (b: string): string => { const [mm] = b.split("/"); return FULL[parseInt(mm, 10) - 1] || b; };
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const byMonth = <T extends Record<string, any>>(d: T[], f: keyof T, a: string): T[] => d.filter(r => r[f] === toFull(a));
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const byMonthMMYYYY = <T extends Record<string, any>>(d: T[], f: keyof T, a: string): T[] => d.filter(r => mmYYYYtoFull(String(r[f])) === toFull(a));
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const unique = <T extends Record<string, any>>(rows: T[], key: string): string[] =>
    [...new Set(rows.map(r => r[key]).filter((v): v is string => typeof v === "string" && v !== ""))].sort();
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const applyFilters = <T extends Record<string, any>>(rows: T[], filters: Record<string, string>): T[] => {
    const active = Object.entries(filters).filter(([, v]) => v);
    if (active.length === 0) return rows;
    return rows.filter(r => active.every(([k, v]) => String(r[k]) === v));
};
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const sortByDate = <T extends Record<string, any>>(rows: T[], key: string): T[] => {
    const parse = (d: string) => { const p=d.split('/').map(Number); return p.length===3?p[2]*10000+p[1]*100+p[0]:0; };
    return [...rows].sort((a,b)=>parse(String(b[key]))-parse(String(a[key])));
};
interface FilterDef { key: string; label: string; options: string[] }

/* ── UI ───────────────────────────────────────────────────────────────────── */

const CP = ({p,size=48,sw=4}: {p: number; size?: number; sw?: number}) => {
    const r=(size-sw)/2,c=2*Math.PI*r,mn=Math.min(p,100),o=c-(mn/100)*c;
    const cl=p>100?"#ef4444":p>75?"#f59e0b":"#18181b";
    return (<svg width={size} height={size} className="transform -rotate-90">
        <circle cx={size/2} cy={size/2} r={r} fill="none" stroke="#e4e4e7" strokeWidth={sw}/>
        <circle cx={size/2} cy={size/2} r={r} fill="none" stroke={cl} strokeWidth={sw} strokeDasharray={c} strokeDashoffset={o} strokeLinecap="round" style={{transition:"stroke-dashoffset .6s ease"}}/>
    </svg>);
};

const LP = ({p}: {p: number}) => {
    const cl=p>90?"#ef4444":p>60?"#f59e0b":"#18181b";
    return (<div className="w-full h-1.5 bg-zinc-100 rounded-full overflow-hidden">
        <div className="h-full rounded-full transition-all duration-500" style={{width:`${Math.min(p,100)}%`,backgroundColor:cl}}/>
    </div>);
};

type BadgeVariant = "default" | "success" | "danger" | "warning";
const B = ({children,v="default"}: {children: ReactNode; v?: BadgeVariant}) => {
    const s: Record<BadgeVariant, string> = {default:"bg-zinc-100 text-zinc-600",success:"bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20",danger:"bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/10",warning:"bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20"};
    return <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium ${s[v]}`}>{children}</span>;
};

const SB = ({s}: {s: string}) => s==="Pago"?<B v="success">● Pago</B>:<B v="danger">● Pendente</B>;

const TabsNav = ({tabs,active,onChange}: {tabs: readonly string[]; active: string; onChange: (tab: string) => void}) => (
    <div className="flex items-center gap-1 overflow-x-auto pb-1" style={{scrollbarWidth:"none"}}>
        {tabs.map(t=><button key={t} onClick={()=>onChange(t)} className={`whitespace-nowrap px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${active===t?"bg-zinc-900 text-white shadow-sm":"text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100"}`}>{t}</button>)}
    </div>
);
const MT = ({a,o}: {a: string; o: (tab: string) => void}) => <TabsNav tabs={MONTHS} active={a} onChange={o}/>;

const PAGE_SIZE = 15;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const Tbl = ({cols,data,footer,onRowClick}: {cols: Column[]; data: Record<string, any>[]; footer?: FooterItem[]; onRowClick?: (row: Record<string, any>) => void}) => {
    const [page,setPage]=useState(1);
    const dataKey=data.map(r=>r.id??'').join(',');
    useEffect(()=>setPage(1),[dataKey]);
    const totalPages=Math.ceil(data.length/PAGE_SIZE);
    const rows=totalPages>1?data.slice((page-1)*PAGE_SIZE,page*PAGE_SIZE):data;
    return (
    <div className="rounded-xl border border-zinc-200 overflow-hidden bg-white shadow-sm">
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead><tr className="border-b border-zinc-100 bg-zinc-50/70">
                    {cols.map(c=><th key={c.key} className={`px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider ${c.align==="right"?"text-right":""}`}>{c.label}</th>)}
                </tr></thead>
                <tbody className="divide-y divide-zinc-50">
                {rows.length===0?<tr><td colSpan={cols.length} className="px-4 py-10 text-center text-sm text-zinc-400">Nenhum registro neste mês</td></tr>
                    :rows.map((row,i)=><tr key={row.id||i} onClick={()=>onRowClick?.(row)} className={`hover:bg-zinc-50/60 transition-colors ${onRowClick?"cursor-pointer":""}`}>
                        {cols.map(c=><td key={c.key} className={`px-4 py-3 ${c.align==="right"?"text-right":""}`}>{c.render?c.render(row):row[c.key]}</td>)}
                    </tr>)}
                </tbody>
            </table>
        </div>
        {totalPages>1&&<div className="border-t border-zinc-100 px-4 py-2 flex items-center justify-between">
            <span className="text-xs text-zinc-400">{data.length} registros</span>
            <div className="flex items-center gap-1">
                <button onClick={()=>setPage(p=>Math.max(1,p-1))} disabled={page===1} className="h-7 w-7 rounded-md flex items-center justify-center text-zinc-600 hover:bg-zinc-100 disabled:opacity-30 disabled:cursor-default transition-colors">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <span className="text-xs text-zinc-500 tabular-nums px-2">{page} / {totalPages}</span>
                <button onClick={()=>setPage(p=>Math.min(totalPages,p+1))} disabled={page===totalPages} className="h-7 w-7 rounded-md flex items-center justify-center text-zinc-600 hover:bg-zinc-100 disabled:opacity-30 disabled:cursor-default transition-colors">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
        </div>}
        {footer&&<div className="border-t border-zinc-100 px-4 py-2.5 flex flex-wrap gap-6 text-xs text-zinc-400 bg-zinc-50/40">
            {footer.map((f,i)=><span key={i}><span className="uppercase tracking-wider">{f.label}</span>{" "}<span className="text-zinc-700 font-semibold">{f.value}</span></span>)}
        </div>}
    </div>);
};

const SH = ({title,onAdd,filters,activeFilters,onFilterChange}: {title: string; onAdd?: () => void; filters?: FilterDef[]; activeFilters?: Record<string, string>; onFilterChange?: (k: string, v: string) => void}) => {
    const ac = activeFilters ? Object.values(activeFilters).filter(v => v).length : 0;
    return (
        <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-zinc-900 tracking-tight">{title}</h2>
            <div className="flex items-center gap-2">
                {filters && filters.length > 0 && onFilterChange && (
                    <Popover>
                        <PopoverTrigger asChild>
                            <button className={`inline-flex items-center gap-1.5 h-8 px-3 rounded-md text-sm font-medium border transition-colors ${ac > 0 ? "border-zinc-900 bg-zinc-900 text-white" : "border-zinc-200 text-zinc-600 hover:bg-zinc-50"}`}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                Filtrar
                                {ac > 0 && <span className="ml-0.5 bg-white text-zinc-900 text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">{ac}</span>}
                            </button>
                        </PopoverTrigger>
                        <PopoverContent className="w-64 p-4" align="end">
                            <div className="space-y-3">
                                {filters.map(f => (
                                    <div key={f.key} className="space-y-1">
                                        <label className="text-xs font-medium text-zinc-500 uppercase tracking-wider">{f.label}</label>
                                        <select value={activeFilters?.[f.key] || ""} onChange={e => onFilterChange(f.key, e.target.value)} className="w-full h-8 px-2 rounded-md border border-zinc-200 text-sm text-zinc-700 bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950/10">
                                            <option value="">Todos</option>
                                            {f.options.map(o => <option key={o} value={o}>{o}</option>)}
                                        </select>
                                    </div>
                                ))}
                                {ac > 0 && (
                                    <button onClick={() => filters.forEach(f => onFilterChange(f.key, ""))} className="w-full h-8 rounded-md text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                                        Limpar filtros
                                    </button>
                                )}
                            </div>
                        </PopoverContent>
                    </Popover>
                )}
                {onAdd&&<button onClick={onAdd} className="inline-flex items-center gap-1.5 h-8 px-3 rounded-md text-sm font-medium bg-zinc-900 text-white hover:bg-zinc-800 active:bg-zinc-700 transition-colors">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg> Novo
                </button>}
            </div>
        </div>
    );
};

/* ── APP ──────────────────────────────────────────────────────────────────── */

const currentMonth = MONTHS[new Date().getMonth()];
const currentYear = new Date().getFullYear();
const yearOptions = Array.from({length: 5}, (_, i) => String(currentYear - 2 + i));

export default function FinancasDashboard() {
    const props = usePage<PageProps>().props;
    const { auth, ano, balancoMensal, ganhos: dataGanhos, fixas: dataFixas, variaveis: dataVar, dividas: dataDividas, investimentos: dataInvest, metas: dataMetas, fontes: dataFontes, categorias: dataCategs, formas: dataFormas, configFontes, configCategorias, configFormas } = props;

    const curQ = Math.floor(new Date().getMonth() / 4);
    const [qTab, setQTab] = useState(["1º Quadrimestre","2º Quadrimestre","3º Quadrimestre"][curQ]);
    const [gM,setGM]=useState(currentMonth);
    const [fM,setFM]=useState(currentMonth);
    const [vM,setVM]=useState(currentMonth);
    const [dM,setDM]=useState(currentMonth);
    const [iM,setIM]=useState(currentMonth);
    const [gFilters,setGFilters]=useState<Record<string,string>>({});
    const [fFilters,setFFilters]=useState<Record<string,string>>({});
    const [vFilters,setVFilters]=useState<Record<string,string>>({});
    const [dFilters,setDFilters]=useState<Record<string,string>>({});
    const [iFilters,setIFilters]=useState<Record<string,string>>({});
    const [modal,setModal]=useState(false);
    const [modalGanho,setModalGanho]=useState(false);
    const [modalFixa,setModalFixa]=useState(false);
    const [modalDivida,setModalDivida]=useState(false);
    const [modalInvest,setModalInvest]=useState(false);
    const [editingDV,setEditingDV]=useState<DespesaVariavel|null>(null);
    const [copyDV,setCopyDV]=useState<DespesaFormData|null>(null);
    const [editingGanho,setEditingGanho]=useState<Ganho|null>(null);
    const [editingFixa,setEditingFixa]=useState<DespesaFixa|null>(null);
    const [editingDivida,setEditingDivida]=useState<Divida|null>(null);
    const [editingInvest,setEditingInvest]=useState<Investimento|null>(null);
    const [modalMeta,setModalMeta]=useState(false);
    const [editingMeta,setEditingMeta]=useState<Meta|null>(null);
    const [modalFonte,setModalFonte]=useState(false);
    const [editingFonte,setEditingFonte]=useState<FonteRenda|null>(null);
    const [modalCategoria,setModalCategoria]=useState(false);
    const [editingCategoria,setEditingCategoria]=useState<Categoria|null>(null);
    const [modalForma,setModalForma]=useState(false);
    const [editingForma,setEditingForma]=useState<FormaPagamento|null>(null);

    const [loading, setLoading] = useState(false);
    const [deleteConfirm, setDeleteConfirm] = useState<{ action: () => void } | null>(null);
    const [deleting, setDeleting] = useState(false);

    const closeAll=()=>{setModal(false);setModalGanho(false);setModalFixa(false);setModalDivida(false);setModalInvest(false);setModalMeta(false);setModalFonte(false);setModalCategoria(false);setModalForma(false);setEditingDV(null);setCopyDV(null);setEditingGanho(null);setEditingFixa(null);setEditingDivida(null);setEditingInvest(null);setEditingMeta(null);setEditingFonte(null);setEditingCategoria(null);setEditingForma(null);};

    const rOpts = { preserveScroll: true, preserveState: true, onSuccess: closeAll, onFinish: () => setLoading(false) };

    const submitDV=(data: DespesaFormData)=>{
        setLoading(true);
        if(editingDV) router.put(despesasVariaveis.update(editingDV.id).url, data, rOpts);
        else router.post(despesasVariaveis.store().url, data, rOpts);
    };
    const submitGanho=(data: GanhoFormData)=>{
        setLoading(true);
        if(editingGanho) router.put(ganhos.update(editingGanho.id).url, data, rOpts);
        else router.post(ganhos.store().url, data, rOpts);
    };
    const submitFixa=(data: DespesaFixaFormData)=>{
        setLoading(true);
        if(editingFixa) router.put(despesasFixas.update(editingFixa.id).url, data, rOpts);
        else router.post(despesasFixas.store().url, data, rOpts);
    };
    const submitDivida=(data: DividaFormData)=>{
        setLoading(true);
        if(editingDivida) router.put(dividas.update(editingDivida.id).url, data, rOpts);
        else router.post(dividas.store().url, data, rOpts);
    };
    const submitInvest=(data: InvestimentoFormData)=>{
        setLoading(true);
        if(editingInvest) router.put(investimentos.update(editingInvest.id).url, data, rOpts);
        else router.post(investimentos.store().url, data, rOpts);
    };
    const submitMeta=(data: MetaFormData)=>{
        setLoading(true);
        if(editingMeta) router.put(metas.update(editingMeta.id).url, { ...data }, rOpts);
        else router.post(metas.store().url, { ...data }, rOpts);
    };
    const submitMetaInvest=(data: MetaInvestFormData)=>{
        setLoading(true);
        router.post(metas.investir(data.metaId).url, { valor: data.valor, data: data.data, dataLimite: data.dataLimite || undefined }, rOpts);
    };

    const submitFonte=(data: ConfigFormData)=>{
        setLoading(true);
        if(editingFonte) router.put(fontesRendaRoutes.update(editingFonte.id).url, { nome: data.nome, icone: data.icone, meta_anual: data.valor }, rOpts);
        else router.post(fontesRendaRoutes.store().url, { nome: data.nome, icone: data.icone, meta_anual: data.valor }, rOpts);
    };
    const submitCategoria=(data: ConfigFormData)=>{
        setLoading(true);
        if(editingCategoria) router.put(categoriasRoutes.update(editingCategoria.id).url, { nome: data.nome, icone: data.icone, limite_anual: data.valor }, rOpts);
        else router.post(categoriasRoutes.store().url, { nome: data.nome, icone: data.icone, limite_anual: data.valor }, rOpts);
    };
    const submitForma=(data: ConfigFormData)=>{
        setLoading(true);
        if(editingForma) router.put(formasPagamentoRoutes.update(editingForma.id).url, { nome: data.nome, icone: data.icone, limite_anual: data.valor }, rOpts);
        else router.post(formasPagamentoRoutes.store().url, { nome: data.nome, icone: data.icone, limite_anual: data.valor }, rOpts);
    };

    const requestDelete = (url: string) => {
        closeAll();
        setDeleteConfirm({
            action: () => {
                setDeleting(true);
                router.delete(url, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => setDeleteConfirm(null),
                    onFinish: () => setDeleting(false),
                });
            },
        });
    };

    const requestDeleteDV=()=>{if(editingDV) requestDelete(despesasVariaveis.destroy(editingDV.id).url);};
    const requestDeleteGanho=()=>{if(editingGanho) requestDelete(ganhos.destroy(editingGanho.id).url);};
    const requestDeleteFixa=()=>{if(editingFixa) requestDelete(despesasFixas.destroy(editingFixa.id).url);};
    const requestDeleteDivida=()=>{if(editingDivida) requestDelete(dividas.destroy(editingDivida.id).url);};
    const requestDeleteInvest=()=>{if(editingInvest) requestDelete(investimentos.destroy(editingInvest.id).url);};
    const requestDeleteMeta=()=>{if(editingMeta) requestDelete(metas.destroy(editingMeta.id).url);};
    const requestDeleteFonte=()=>{if(editingFonte) requestDelete(fontesRendaRoutes.destroy(editingFonte.id).url);};
    const requestDeleteCategoria=()=>{if(editingCategoria) requestDelete(categoriasRoutes.destroy(editingCategoria.id).url);};
    const requestDeleteForma=()=>{if(editingForma) requestDelete(formasPagamentoRoutes.destroy(editingForma.id).url);};

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const openEditGanho=(row: Record<string,any>)=>{const r=row as Ganho;setEditingGanho(r);setModalGanho(true);};
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const openEditFixa=(row: Record<string,any>)=>{const r=row as DespesaFixa;setEditingFixa(r);setModalFixa(true);};
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const openEditDV=(row: Record<string,any>)=>{const r=row as DespesaVariavel;setCopyDV(null);setEditingDV(r);setModal(true);};
    // Fecha o modal de edição e reabre como "nova despesa" pré-preenchida com os dados da despesa atual
    const copyEditDV=()=>{
        if(!editingDV) return;
        setCopyDV({descricao:editingDV.descricao,categoria:editingDV.categoria,valor:String(editingDV.valor),data:editingDV.data,forma:editingDV.forma,balanco:editingDV.balanco,parcelas:"1",dataLimite:""});
        setEditingDV(null);
    };
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const openEditDivida=(row: Record<string,any>)=>{const r=row as Divida;setEditingDivida(r);setModalDivida(true);};
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const openEditInvest=(row: Record<string,any>)=>{const r=row as Investimento;setEditingInvest(r);setModalInvest(true);};

    const changeAno = (newAno: number) => {
        router.visit('/', { data: { ano: newAno }, preserveState: true });
    };

    const qTabs=["1º Quadrimestre","2º Quadrimestre","3º Quadrimestre"];
    const vis=balancoMensal.slice(qTabs.indexOf(qTab)*4,qTabs.indexOf(qTab)*4+4);
    const gMonth=byMonth(dataGanhos,"balanco",gM);
    const fMonth=byMonth(dataFixas,"balanco",fM);
    const vMonth=byMonthMMYYYY(dataVar,"balanco",vM);
    const dMonth=byMonth(dataDividas,"balanco",dM);
    const iMonth=byMonth(dataInvest,"balanco",iM);
    const gF=sortByDate(applyFilters(gMonth,gFilters),"data");
    const fF=sortByDate(applyFilters(fMonth,fFilters),"vencimento");
    const vF=sortByDate(applyFilters(vMonth,vFilters),"data");
    const dF=sortByDate(applyFilters(dMonth,dFilters),"vencimento");
    const iF=sortByDate(applyFilters(iMonth,iFilters),"data");
    const gFD:FilterDef[]=[{key:"fonte",label:"Fonte de Renda",options:unique(gMonth,"fonte")}];
    const fFD:FilterDef[]=[{key:"categoria",label:"Categoria",options:unique(fMonth,"categoria")},{key:"status",label:"Status",options:unique(fMonth,"status")},{key:"forma",label:"Forma",options:unique(fMonth,"forma")}];
    const vFD:FilterDef[]=[{key:"categoria",label:"Categoria",options:unique(vMonth,"categoria")},{key:"forma",label:"Forma de Pagamento",options:unique(vMonth,"forma")}];
    const dFD:FilterDef[]=[{key:"destino",label:"Destino",options:unique(dMonth,"destino")},{key:"status",label:"Status",options:unique(dMonth,"status")}];
    const iFD:FilterDef[]=[{key:"tipoAtivo",label:"Tipo de Ativo",options:unique(iMonth,"tipoAtivo")},{key:"frequencia",label:"Frequência",options:unique(iMonth,"frequencia")}];
    const tR=balancoMensal.reduce((s,m)=>s+m.receita,0);
    const tD=balancoMensal.reduce((s,m)=>s+m.despesa,0);
    const tB=tR-tD;

    return (
        <div className="min-h-screen bg-zinc-50/50" style={{fontFamily:"'Inter',-apple-system,BlinkMacSystemFont,sans-serif"}}>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>

            <header className="sticky top-0 z-40 bg-white/80 backdrop-blur-lg border-b border-zinc-200/80">
                <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-lg bg-zinc-900 flex items-center justify-center text-white text-sm font-bold">F</div>
                        <div><h1 className="text-lg font-bold text-zinc-900 tracking-tight leading-tight">Finanças</h1><p className="text-xs text-zinc-400">Controle financeiro · {ano}</p></div>
                    </div>
                    <div className="flex items-center gap-5">
                        <div className="hidden md:flex items-center gap-5">
                            <div className="text-right"><p className="text-[11px] text-zinc-400 uppercase tracking-wider">Receita</p><p className="text-sm font-bold text-emerald-600">{fmt(tR)}</p></div>
                            <div className="text-right"><p className="text-[11px] text-zinc-400 uppercase tracking-wider">Despesa</p><p className="text-sm font-bold text-red-500">{fmt(tD)}</p></div>
                            <div className="h-8 w-px bg-zinc-200"/>
                            <div className="text-right"><p className="text-[11px] text-zinc-400 uppercase tracking-wider">Balanço</p><p className={`text-sm font-bold ${tB>=0?"text-emerald-600":"text-red-600"}`}>{fmt(tB)}</p></div>
                        </div>
                        <div className="h-8 w-px bg-zinc-200 hidden md:block"/>
                        <Combobox items={yearOptions} value={String(ano)} onValueChange={val => { if(val) changeAno(Number(val)); }}>
                            <ComboboxInput placeholder="Ano" className="w-23 h-8 text-sm font-medium" />
                            <ComboboxContent>
                                <ComboboxEmpty>Nenhum ano.</ComboboxEmpty>
                                <ComboboxList>
                                    {item => <ComboboxItem key={item} value={item}>{item}</ComboboxItem>}
                                </ComboboxList>
                            </ComboboxContent>
                        </Combobox>
                        {auth.user && <>
                            <div className="h-8 w-px bg-zinc-200"/>
                            <div className="flex items-center gap-3">
                                <span className="text-sm font-medium text-zinc-700">{auth.user.name}</span>
                                <Link href={logout().url} method="post" as="button" className="text-sm text-zinc-400 hover:text-zinc-700 transition-colors">Sair</Link>
                            </div>
                        </>}
                    </div>
                </div>
            </header>

            <div className="max-w-7xl mx-auto px-6 py-8 space-y-10">

                {/* BALANÇO MENSAL */}
                <section><SH title="Balanço Mensal"/><TabsNav tabs={qTabs} active={qTab} onChange={setQTab}/>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                        {vis.map(m=>{const p=Math.round((m.despesa/m.receita)*100)||0,b=m.receita-m.despesa;return(
                            <div key={m.mes} className="rounded-xl border border-zinc-200 bg-white p-5 hover:shadow-md hover:border-zinc-300 transition-all">
                                <div className="flex items-center justify-between mb-4"><h3 className="font-semibold text-zinc-900">{m.mes}</h3><span className={`text-xs font-mono font-semibold px-2 py-0.5 rounded-md ${p>90?"bg-red-50 text-red-600":"bg-zinc-100 text-zinc-600"}`}>{p}%</span></div>
                                <div className="space-y-1.5 mb-4">
                                    <div className="flex justify-between text-sm"><span className="text-emerald-600 font-medium">Receita</span><span className="text-zinc-700 font-mono">{fmt(m.receita)}</span></div>
                                    <div className="flex justify-between text-sm"><span className="text-red-500 font-medium">Despesa</span><span className="text-zinc-700 font-mono">{fmt(m.despesa)}</span></div>
                                </div>
                                <LP p={p}/>
                                <div className="flex justify-between items-center mt-3 pt-3 border-t border-zinc-100"><span className="text-xs text-zinc-400">Balanço</span><span className={`text-sm font-bold font-mono ${b>=0?"text-emerald-600":"text-red-600"}`}>{fmt(b)}</span></div>
                            </div>);})}
                    </div>
                </section>

                {/* GANHOS */}
                <section><SH title="Ganhos" onAdd={()=>{setEditingGanho(null);setModalGanho(true);}} filters={gFD} activeFilters={gFilters} onFilterChange={(k,v)=>setGFilters(p=>({...p,[k]:v}))}/><MT a={gM} o={setGM}/>
                    <div className="mt-3"><Tbl cols={[
                        {key:"descricao",label:"Descrição",render:r=><span className="font-medium text-zinc-900">{r.descricao}</span>},
                        {key:"fonte",label:"Fonte de Renda",render:r=><B>{r.fonte}</B>},
                        {key:"data",label:"Data",render:r=><span className="text-zinc-500">{r.data}</span>},
                        {key:"valor",label:"Valor",align:"right",render:r=><span className="font-mono font-semibold text-emerald-600">{fmt(r.valor)}</span>},
                    ]} data={gF} footer={[{label:"Contagem",value:gF.length},{label:"Soma",value:fmt(gF.reduce((s,g)=>s+g.valor,0))}]} onRowClick={openEditGanho}/></div>
                </section>

                {/* DESPESAS FIXAS */}
                <section><SH title="Despesas Fixas" onAdd={()=>{setEditingFixa(null);setModalFixa(true);}} filters={fFD} activeFilters={fFilters} onFilterChange={(k,v)=>setFFilters(p=>({...p,[k]:v}))}/><MT a={fM} o={setFM}/>
                    <div className="mt-3"><Tbl cols={[
                        {key:"descricao",label:"Descrição",render:r=><span className="font-medium text-zinc-900">{r.descricao}</span>},
                        {key:"categoria",label:"Categoria",render:r=><B>{r.categoria}</B>},
                        {key:"valor",label:"Valor",align:"right",render:r=><span className="font-mono">{fmt(r.valor)}</span>},
                        {key:"vencimento",label:"Vencimento",render:r=><span className="text-zinc-500">{r.vencimento}</span>},
                        {key:"status",label:"Status",render:r=><SB s={r.status}/>},
                        {key:"dataPgto",label:"Data Pgto",render:r=><span className="text-zinc-500">{r.dataPgto}</span>},
                        {key:"forma",label:"Forma",render:r=>r.forma?<B>{r.forma}</B>:<span className="text-zinc-300">—</span>},
                    ]} data={fF} footer={[{label:"Contagem",value:fF.length},{label:"Soma",value:fmt(fF.reduce((s,d)=>s+d.valor,0))},{label:"Concluídos",value:`${fF.length>0?Math.round((fF.filter(d=>d.status==="Pago").length/fF.length)*100):0}%`}]} onRowClick={openEditFixa}/></div>
                </section>

                {/* DESPESAS VARIÁVEIS */}
                <section><SH title="Despesas Variáveis" onAdd={()=>{setEditingDV(null);setCopyDV(null);setModal(true);}} filters={vFD} activeFilters={vFilters} onFilterChange={(k,v)=>setVFilters(p=>({...p,[k]:v}))}/><MT a={vM} o={setVM}/>
                    <div className="mt-3"><Tbl cols={[
                        {key:"descricao",label:"Descrição",render:r=><span className="font-medium text-zinc-900">{r.descricao}</span>},
                        {key:"categoria",label:"Categoria",render:r=><B>{r.categoria}</B>},
                        {key:"valor",label:"Valor",align:"right",render:r=><span className="font-mono">{fmt(r.valor)}</span>},
                        {key:"data",label:"Data",render:r=><span className="text-zinc-500">{r.data}</span>},
                        {key:"forma",label:"Forma de Pagamento",render:r=>r.forma?<B>{r.forma}</B>:<span className="text-zinc-300">—</span>},
                    ]} data={vF} footer={[{label:"Contagem",value:vF.length},{label:"Soma",value:fmt(vF.reduce((s,d)=>s+d.valor,0))}]} onRowClick={openEditDV}/></div>
                </section>

                {/* DÍVIDAS */}
                <section><SH title="Dívidas" onAdd={()=>{setEditingDivida(null);setModalDivida(true);}} filters={dFD} activeFilters={dFilters} onFilterChange={(k,v)=>setDFilters(p=>({...p,[k]:v}))}/><MT a={dM} o={setDM}/>
                    <div className="mt-3"><Tbl cols={[
                        {key:"descricao",label:"Descrição",render:r=><span className="font-medium text-zinc-900">{r.descricao}</span>},
                        {key:"destino",label:"Destino",render:r=><B>{r.destino}</B>},
                        {key:"valor",label:"Valor",align:"right",render:r=><span className="font-mono">{fmt(r.valor)}</span>},
                        {key:"vencimento",label:"Vencimento",render:r=><span className="text-zinc-500">{r.vencimento}</span>},
                        {key:"status",label:"Status",render:r=><SB s={r.status}/>},
                    ]} data={dF} footer={[{label:"Contagem",value:dF.length},{label:"Soma",value:fmt(dF.reduce((s,d)=>s+d.valor,0))},{label:"Concluídos",value:`${dF.length>0?Math.round((dF.filter(d=>d.status==="Pago").length/dF.length)*100):0}%`}]} onRowClick={openEditDivida}/></div>
                </section>

                {/* INVESTIMENTOS */}
                <section><SH title="Investimentos" onAdd={()=>{setEditingInvest(null);setModalInvest(true);}} filters={iFD} activeFilters={iFilters} onFilterChange={(k,v)=>setIFilters(p=>({...p,[k]:v}))}/><MT a={iM} o={setIM}/>
                    <div className="mt-3"><Tbl cols={[
                        {key:"produto",label:"Produto",render:r=><span className="font-mono font-semibold text-zinc-900">{r.produto}</span>},
                        {key:"empresa",label:"Empresa",render:r=><span className="text-zinc-600">{r.empresa}</span>},
                        {key:"valor",label:"Valor",align:"right",render:r=><span className="font-mono">{fmt(r.valor)}</span>},
                        {key:"quantidade",label:"Qtd",align:"right"},
                        {key:"valorTotal",label:"Total",align:"right",render:r=><span className="font-mono font-semibold">{fmt(r.valorTotal)}</span>},
                        {key:"tipoAtivo",label:"Tipo",render:r=><B v={r.tipoAtivo.includes("Fundo")?"warning":"success"}>{r.tipoAtivo}</B>},
                        {key:"provento",label:"Provento",align:"right",render:r=><span className="font-mono text-emerald-600">{fmt(r.provento)}</span>},
                        {key:"frequencia",label:"Frequência",render:r=><B>{r.frequencia}</B>},
                    ]} data={iF} footer={[{label:"Contagem",value:iF.length},{label:"Média",value:fmt(iF.length>0?iF.reduce((s,x)=>s+x.valor,0)/iF.length:0)},{label:"Soma Total",value:fmt(iF.reduce((s,x)=>s+x.valorTotal,0))}]} onRowClick={openEditInvest}/></div>
                </section>

                {/* METAS FINANCEIRAS */}
                <section><SH title="Metas Financeiras" onAdd={()=>{setEditingMeta(null);setModalMeta(true);}}/>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {dataMetas.map(m=><div key={m.id} onClick={()=>{setEditingMeta(m);setModalMeta(true);}} className="cursor-pointer rounded-xl border border-zinc-200 bg-white p-6 hover:shadow-md hover:border-zinc-300 transition-all">
                            <div className="flex items-center gap-4 mb-5">
                                <div className="relative"><CP p={m.percent} size={56} sw={4}/><span className="absolute inset-0 flex items-center justify-center text-xs font-bold text-zinc-700">{m.percent}%</span></div>
                                <h3 className="font-semibold text-zinc-900 flex items-center gap-2"><IconPreview name={m.icone || "Gem"} className="size-5 text-zinc-700"/>{m.nome}</h3>
                            </div>
                            <div className="space-y-2.5">
                                <div className="flex justify-between text-sm"><span className="text-zinc-400">Valor</span><span className="font-mono font-semibold text-zinc-900">{fmt(m.valor)}</span></div>
                                <div className="flex justify-between text-sm"><span className="text-zinc-400">Investido</span><span className="font-mono text-emerald-600">{fmt(m.investido)}</span></div>
                                <div className="h-px bg-zinc-100"/><div className="flex justify-between text-sm"><span className="text-zinc-400">Faltante</span><span className="font-mono text-amber-600">{fmt(m.faltante)}</span></div>
                            </div>
                        </div>)}
                    </div>
                </section>

                {/* FONTES DE RENDA */}
                <section><SH title="Fontes de Renda" onAdd={()=>{setEditingFonte(null);setModalFonte(true);}}/>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {dataFontes.map(f=><div key={f.id} onClick={()=>{setEditingFonte(f);setModalFonte(true);}} className="cursor-pointer rounded-xl border border-zinc-200 bg-white p-6 hover:shadow-md hover:border-zinc-300 transition-all">
                            <div className="flex items-center gap-4 mb-5">
                                <div className="relative"><CP p={f.percent} size={56} sw={4}/><span className="absolute inset-0 flex items-center justify-center text-xs font-bold text-zinc-700">{f.percent}%</span></div>
                                <h3 className="font-semibold text-zinc-900 flex items-center gap-2"><IconPreview name={f.icone || "Briefcase"} className="size-5 text-zinc-700"/>{f.nome}</h3>
                            </div>
                            <div className="space-y-2.5">
                                <div className="flex justify-between text-sm"><span className="text-zinc-400">Meta Anual</span><span className="font-mono font-semibold text-zinc-900">{fmt(f.metaAnual)}</span></div>
                                <div className="flex justify-between text-sm"><span className="text-emerald-600 font-medium">Receita Anual</span><span className="font-mono text-emerald-600">{fmt(f.receitaAnual)}</span></div>
                            </div>
                        </div>)}
                    </div>
                </section>

                {/* CATEGORIAS */}
                <section><SH title="Categorias" onAdd={()=>{setEditingCategoria(null);setModalCategoria(true);}}/>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {dataCategs.map(c=><div key={c.id} onClick={()=>{setEditingCategoria(c);setModalCategoria(true);}} className="cursor-pointer rounded-xl border border-zinc-200 bg-white p-5 hover:shadow-md hover:border-zinc-300 transition-all">
                            <div className="flex items-center gap-3 mb-3">
                                <div className="relative"><CP p={c.pct} size={40} sw={3}/><span className="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-zinc-500">{c.pct>0?`${c.pct}%`:"—"}</span></div>
                                <h3 className="font-semibold text-zinc-900 text-sm flex items-center gap-1.5"><IconPreview name={c.icone || "ShoppingBag"} className="size-4 text-zinc-600"/>{c.nome}</h3>
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex justify-between text-xs"><span className="text-zinc-400">Limite Anual</span><span className="font-mono text-zinc-600">{c.lim?fmt(c.lim):"—"}</span></div>
                                <div className="flex justify-between text-xs"><span className="text-red-500">Despesa Anual</span><span className="font-mono text-red-500">{fmt(c.desp)}</span></div>
                            </div>
                        </div>)}
                    </div>
                </section>

                {/* FORMAS DE PAGAMENTO */}
                <section><SH title="Formas de Pagamento" onAdd={()=>{setEditingForma(null);setModalForma(true);}}/>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {dataFormas.map(f=><div key={f.id} onClick={()=>{setEditingForma(f);setModalForma(true);}} className="cursor-pointer rounded-xl border border-zinc-200 bg-white p-5 hover:shadow-md hover:border-zinc-300 transition-all">
                            <div className="flex items-center gap-3 mb-3">
                                <div className="relative"><CP p={f.pct} size={40} sw={3}/><span className="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-zinc-500">{f.pct}%</span></div>
                                <h3 className="font-semibold text-zinc-900 text-sm flex items-center gap-1.5"><IconPreview name={f.icone || "CreditCard"} className="size-4 text-zinc-600"/>{f.nome}</h3>
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex justify-between text-xs"><span className="text-zinc-400">Limite Anual</span><span className="font-mono text-zinc-600">{fmt(f.lim)}</span></div>
                                <div className="flex justify-between text-xs"><span className="text-red-500">Despesa Anual</span><span className="font-mono text-red-500">{fmt(f.desp)}</span></div>
                            </div>
                        </div>)}
                    </div>
                </section>
            </div>

            <DespesaVariavelModal key={editingDV?`edit-${editingDV.id}`:copyDV?"copy":"new"} open={modal} onClose={closeAll} onSubmit={submitDV} loading={loading}
                categorias={configCategorias} formas={configFormas}
                initialData={editingDV?{descricao:editingDV.descricao,categoria:editingDV.categoria,valor:String(editingDV.valor),data:editingDV.data,forma:editingDV.forma,balanco:editingDV.balanco,parcelas:"1",dataLimite:""}:undefined}
                copyData={copyDV??undefined}
                onDelete={editingDV?requestDeleteDV:undefined}
                onCopy={editingDV?copyEditDV:undefined}/>
            <GanhoModal open={modalGanho} onClose={closeAll} onSubmit={submitGanho} loading={loading}
                fontes={configFontes}
                initialData={editingGanho?{descricao:editingGanho.descricao,fonte:editingGanho.fonte,data:editingGanho.data,valor:String(editingGanho.valor),dataLimite:""}:undefined}
                onDelete={editingGanho?requestDeleteGanho:undefined}/>
            <DespesaFixaModal open={modalFixa} onClose={closeAll} onSubmit={submitFixa} loading={loading}
                categorias={configCategorias} formas={configFormas}
                initialData={editingFixa?{descricao:editingFixa.descricao,categoria:editingFixa.categoria,valor:String(editingFixa.valor),vencimento:editingFixa.vencimento,status:editingFixa.status,dataPgto:editingFixa.dataPgto,forma:editingFixa.forma}:undefined}
                onDelete={editingFixa?requestDeleteFixa:undefined}/>
            <DividaModal open={modalDivida} onClose={closeAll} onSubmit={submitDivida} loading={loading}
                initialData={editingDivida?{descricao:editingDivida.descricao,destino:editingDivida.destino,valor:String(editingDivida.valor),vencimento:editingDivida.vencimento,status:editingDivida.status,dataLimite:""}:undefined}
                onDelete={editingDivida?requestDeleteDivida:undefined}/>
            <InvestimentoModal open={modalInvest} onClose={closeAll} onSubmit={submitInvest} loading={loading}
                onSubmitMeta={submitMetaInvest}
                metas={dataMetas.map(m=>({id:m.id,nome:m.nome,valor:m.valor,investido:m.investido}))}
                initialData={editingInvest?{produto:editingInvest.produto,empresa:editingInvest.empresa,valor:String(editingInvest.valor),quantidade:String(editingInvest.quantidade),tipoAtivo:editingInvest.tipoAtivo,provento:String(editingInvest.provento),frequencia:editingInvest.frequencia,data:editingInvest.data,dataLimite:""}:undefined}
                onDelete={editingInvest?requestDeleteInvest:undefined}/>
            <MetaModal open={modalMeta} onClose={closeAll} onSubmit={submitMeta} loading={loading}
                initialData={editingMeta?{nome:editingMeta.nome,icone:editingMeta.icone||"Gem",valor:String(editingMeta.valor)}:undefined}
                onDelete={editingMeta?requestDeleteMeta:undefined}/>

            <ConfigModal open={modalFonte} onClose={closeAll} onSubmit={submitFonte} loading={loading}
                title="Fonte de Renda" valorLabel="Meta Anual (R$)" valorPlaceholder="0,00" defaultIcon="Briefcase"
                initialData={editingFonte?{nome:editingFonte.nome,icone:editingFonte.icone||"Briefcase",valor:String(editingFonte.metaAnual||"")}:undefined}
                onDelete={editingFonte?requestDeleteFonte:undefined}/>
            <ConfigModal open={modalCategoria} onClose={closeAll} onSubmit={submitCategoria} loading={loading}
                title="Categoria" valorLabel="Limite Anual (R$)" valorPlaceholder="0,00" defaultIcon="ShoppingBag"
                initialData={editingCategoria?{nome:editingCategoria.nome,icone:editingCategoria.icone||"ShoppingBag",valor:editingCategoria.lim!=null?String(editingCategoria.lim):""}:undefined}
                onDelete={editingCategoria?requestDeleteCategoria:undefined}/>
            <ConfigModal open={modalForma} onClose={closeAll} onSubmit={submitForma} loading={loading}
                title="Forma de Pagamento" valorLabel="Limite Anual (R$)" valorPlaceholder="0,00" defaultIcon="CreditCard"
                initialData={editingForma?{nome:editingForma.nome,icone:editingForma.icone||"CreditCard",valor:String(editingForma.lim||"")}:undefined}
                onDelete={editingForma?requestDeleteForma:undefined}/>

            {/* MODAL DE CONFIRMAÇÃO DE EXCLUSÃO */}
            {deleteConfirm && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center" style={{ animation: "fi .15s ease" }}>
                    <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={() => !deleting && setDeleteConfirm(null)} />
                    <div className="relative bg-white rounded-xl shadow-2xl border border-zinc-200 w-full max-w-sm mx-4 p-6" style={{ animation: "si .2s ease" }}>
                        <h3 className="text-base font-semibold text-zinc-900">Excluir registro?</h3>
                        <p className="text-sm text-zinc-500 mt-2">Essa ação não pode ser desfeita. O registro será removido permanentemente.</p>
                        <div className="flex justify-end gap-3 mt-6">
                            <button onClick={() => setDeleteConfirm(null)} disabled={deleting} className="h-9 px-4 rounded-md border border-zinc-200 text-sm font-medium text-zinc-700 hover:bg-zinc-50 transition-colors disabled:opacity-50">
                                Cancelar
                            </button>
                            <button onClick={deleteConfirm.action} disabled={deleting} className="h-9 px-4 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50 flex items-center gap-2">
                                {deleting ? <><Spinner className="size-4" /> Excluindo...</> : "Excluir"}
                            </button>
                        </div>
                    </div>
                    <style>{`@keyframes fi{from{opacity:0}to{opacity:1}}@keyframes si{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}`}</style>
                </div>
            )}
        </div>
    );
}
