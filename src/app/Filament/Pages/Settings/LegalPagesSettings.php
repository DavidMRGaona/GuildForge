<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Concerns\ManagesPageSettings;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
final class LegalPagesSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesPageSettings;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.pages.settings.legal-pages-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.legal.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.pages');
    }

    public function getTitle(): string
    {
        return __('filament.pages.legal.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(SettingsServiceInterface $settingsService): void
    {
        $this->form->fill($this->loadSettings($settingsService));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make(__('filament.pages.legal.tabs.entity'))
                            ->schema([
                                TextInput::make('legal_entity_name')
                                    ->label(__('filament.pages.legal.fields.entity_name'))
                                    ->maxLength(255),
                                TextInput::make('legal_entity_cif')
                                    ->label(__('filament.pages.legal.fields.entity_cif'))
                                    ->maxLength(20),
                                TextInput::make('legal_entity_address')
                                    ->label(__('filament.pages.legal.fields.entity_address'))
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                TextInput::make('legal_entity_email')
                                    ->label(__('filament.pages.legal.fields.entity_email'))
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('legal_entity_phone')
                                    ->label(__('filament.pages.legal.fields.entity_phone'))
                                    ->tel()
                                    ->maxLength(20),
                                TextInput::make('legal_entity_dpo_email')
                                    ->label(__('filament.pages.legal.fields.entity_dpo_email'))
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('legal_entity_registry')
                                    ->label(__('filament.pages.legal.fields.entity_registry'))
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                TextInput::make('legal_entity_domain')
                                    ->label(__('filament.pages.legal.fields.entity_domain'))
                                    ->maxLength(255),
                            ])->columns(2),

                        Tabs\Tab::make(__('filament.pages.legal.tabs.privacy'))
                            ->schema([
                                Toggle::make('legal_privacy_published')
                                    ->label(__('filament.pages.legal.fields.published')),
                                RichEditor::make('legal_privacy_content')
                                    ->label(__('filament.pages.legal.fields.content'))
                                    ->helperText(__('filament.pages.legal.fields.content_help'))
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('filament.pages.legal.tabs.notice'))
                            ->schema([
                                Toggle::make('legal_notice_published')
                                    ->label(__('filament.pages.legal.fields.published')),
                                RichEditor::make('legal_notice_content')
                                    ->label(__('filament.pages.legal.fields.content'))
                                    ->helperText(__('filament.pages.legal.fields.content_help'))
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('filament.pages.legal.tabs.cookies'))
                            ->schema([
                                Toggle::make('legal_cookies_published')
                                    ->label(__('filament.pages.legal.fields.published')),
                                RichEditor::make('legal_cookies_content')
                                    ->label(__('filament.pages.legal.fields.content'))
                                    ->helperText(__('filament.pages.legal.fields.content_help'))
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('filament.pages.legal.tabs.terms'))
                            ->schema([
                                Toggle::make('legal_terms_published')
                                    ->label(__('filament.pages.legal.fields.published')),
                                RichEditor::make('legal_terms_content')
                                    ->label(__('filament.pages.legal.fields.content'))
                                    ->helperText(__('filament.pages.legal.fields.content_help'))
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(SettingsServiceInterface $settingsService): void
    {
        $formData = $this->form->getState();
        $this->saveSettings($settingsService, $formData);

        Notification::make()
            ->title(__('filament.pages.legal.saved'))
            ->success()
            ->send();
    }

    /**
     * Get the list of setting keys this page manages.
     *
     * @return array<string>
     */
    protected function getSettingsKeys(): array
    {
        return [
            'legal_entity_name',
            'legal_entity_cif',
            'legal_entity_address',
            'legal_entity_email',
            'legal_entity_phone',
            'legal_entity_dpo_email',
            'legal_entity_registry',
            'legal_entity_domain',
            'legal_privacy_published',
            'legal_privacy_content',
            'legal_notice_published',
            'legal_notice_content',
            'legal_cookies_published',
            'legal_cookies_content',
            'legal_terms_published',
            'legal_terms_content',
        ];
    }

    /**
     * Get the list of field names that need JSON encoding/decoding.
     *
     * @return array<string>
     */
    protected function getJsonFields(): array
    {
        return [];
    }

    /**
     * Get the list of field names that are image uploads.
     *
     * @return array<string>
     */
    protected function getImageFields(): array
    {
        return [];
    }

    /**
     * Get the default values for settings.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultSettings(): array
    {
        return [
            'legal_entity_name' => 'GuildForge',
            'legal_entity_cif' => 'G00000000',
            'legal_entity_address' => 'Calle Ejemplo, 1, 28001 Madrid',
            'legal_entity_email' => 'contacto@guildforge.es',
            'legal_entity_phone' => '+34 600 000 000',
            'legal_entity_dpo_email' => 'privacidad@guildforge.es',
            'legal_entity_registry' => 'Inscrita en el Registro de Asociaciones con el número 000000',
            'legal_entity_domain' => 'www.guildforge.es',
            'legal_privacy_published' => '',
            'legal_privacy_content' => $this->getDefaultPrivacyContent(),
            'legal_notice_published' => '',
            'legal_notice_content' => $this->getDefaultNoticeContent(),
            'legal_cookies_published' => '',
            'legal_cookies_content' => $this->getDefaultCookiesContent(),
            'legal_terms_published' => '',
            'legal_terms_content' => $this->getDefaultTermsContent(),
        ];
    }

    private function getDefaultPrivacyContent(): string
    {
        return <<<'HTML'
<h2>1. Normativa aplicable</h2>
<p>La presente política de privacidad se rige por la siguiente normativa:</p>
<ul>
<li>Reglamento (UE) 2016/679 del Parlamento Europeo y del Consejo, de 27 de abril de 2016 (RGPD).</li>
<li>Ley Orgánica 3/2018, de 5 de diciembre, de protección de datos personales y garantía de los derechos digitales (LOPDGDD).</li>
<li>Ley 34/2002, de 11 de julio, de servicios de la sociedad de la información y de comercio electrónico (LSSI-CE).</li>
</ul>

<h2>2. Responsable del tratamiento</h2>
<p><strong>{{nombre}}</strong><br>
CIF/NIF: {{cif}}<br>
Domicilio social: {{direccion}}<br>
Correo electrónico: {{email}}<br>
Teléfono: {{telefono}}<br>
Datos registrales: {{registro}}</p>

<h2>3. Delegado de protección de datos</h2>
<p>Puede contactar con el delegado de protección de datos o responsable de privacidad en la siguiente dirección de correo electrónico: {{email_dpo}}</p>

<h2>4. Principios aplicados al tratamiento de datos</h2>
<p>En el tratamiento de sus datos personales, aplicamos los siguientes principios conforme al artículo 5 del RGPD:</p>
<ul>
<li><strong>Licitud, lealtad y transparencia:</strong> siempre requeriremos su consentimiento para el tratamiento de sus datos personales para uno o varios fines específicos, de los que le informaremos previamente.</li>
<li><strong>Minimización de datos:</strong> solo solicitaremos los datos estrictamente necesarios para los fines para los que los requerimos.</li>
<li><strong>Limitación del plazo de conservación:</strong> los datos se mantendrán durante el tiempo estrictamente necesario para los fines del tratamiento.</li>
<li><strong>Integridad y confidencialidad:</strong> sus datos serán tratados de forma que se garantice una seguridad adecuada, incluida la protección contra el tratamiento no autorizado o ilícito y contra su pérdida, destrucción o daño accidental.</li>
<li><strong>Exactitud:</strong> los datos serán exactos y, si fuera necesario, actualizados.</li>
</ul>

<h2>5. Finalidades del tratamiento</h2>
<p>Tratamos sus datos personales para las siguientes finalidades:</p>
<ul>
<li><strong>Gestión de socios y miembros:</strong> administración de altas, bajas y datos de los miembros de la asociación.</li>
<li><strong>Organización de eventos y actividades:</strong> gestión de inscripciones, comunicaciones relacionadas con eventos, torneos y actividades organizadas.</li>
<li><strong>Gestión del sitio web:</strong> administración de cuentas de usuario, publicación de contenidos y mantenimiento del sitio web {{dominio}}.</li>
<li><strong>Comunicaciones:</strong> envío de boletines informativos, notificaciones sobre actividades y novedades de la asociación.</li>
<li><strong>Galería fotográfica:</strong> publicación de fotografías de eventos y actividades para difusión de las actividades de la asociación.</li>
<li><strong>Cumplimiento de obligaciones legales:</strong> atención a las obligaciones legales que correspondan.</li>
</ul>

<h2>6. Base legal del tratamiento</h2>
<p>La base legal para el tratamiento de sus datos es la siguiente:</p>
<ul>
<li><strong>Consentimiento del interesado</strong> (art. 6.1.a RGPD): para el envío de comunicaciones comerciales, publicación de imágenes y tratamientos que requieran consentimiento expreso.</li>
<li><strong>Ejecución de un contrato o relación asociativa</strong> (art. 6.1.b RGPD): para la gestión de la relación como socio o miembro de la asociación.</li>
<li><strong>Interés legítimo</strong> (art. 6.1.f RGPD): para la difusión de las actividades de la asociación y la mejora de nuestros servicios.</li>
<li><strong>Cumplimiento de obligaciones legales</strong> (art. 6.1.c RGPD): cuando el tratamiento sea necesario para cumplir con una obligación legal.</li>
</ul>

<h2>7. Categorías de datos personales</h2>
<p>Las categorías de datos que tratamos son:</p>
<ul>
<li><strong>Datos identificativos:</strong> nombre, apellidos, DNI/NIE, nombre de usuario.</li>
<li><strong>Datos de contacto:</strong> dirección postal, correo electrónico, teléfono.</li>
<li><strong>Datos de imagen:</strong> fotografías tomadas durante eventos y actividades.</li>
<li><strong>Datos de navegación:</strong> dirección IP, tipo de navegador, páginas visitadas (véase nuestra política de cookies).</li>
</ul>

<h2>8. Plazo de conservación</h2>
<p>Los datos personales se conservarán mientras se mantenga la relación asociativa o de usuario, y una vez finalizada, durante los plazos legalmente establecidos. En concreto:</p>
<ul>
<li>Datos de socios: durante la vigencia de la relación asociativa y 5 años tras la baja.</li>
<li>Datos de usuarios web: mientras la cuenta esté activa y 2 años tras la última actividad.</li>
<li>Datos fiscales y contables: 6 años conforme al Código de Comercio.</li>
<li>Imágenes y fotografías: mientras no se solicite su retirada y exista base legal para su publicación.</li>
</ul>

<h2>9. Destinatarios de los datos</h2>
<p>No se cederán datos personales a terceros, salvo obligación legal. No se realizarán cesiones de datos salvo en los siguientes supuestos:</p>
<ul>
<li>Administraciones públicas cuando así lo exija la normativa vigente.</li>
<li>Proveedores de servicios que actúen como encargados del tratamiento, con los que se ha suscrito el correspondiente contrato de encargo de tratamiento conforme al artículo 28 del RGPD.</li>
</ul>

<h2>10. Transferencias internacionales</h2>
<p>No se realizan transferencias internacionales de datos a países fuera del Espacio Económico Europeo. En caso de que fuera necesario en el futuro, se garantizará el cumplimiento de las garantías adecuadas conforme al capítulo V del RGPD.</p>

<h2>11. Derechos del interesado</h2>
<p>Como titular de sus datos personales, tiene derecho a:</p>
<ul>
<li><strong>Derecho de acceso:</strong> conocer si estamos tratando sus datos y, en tal caso, obtener una copia de los mismos.</li>
<li><strong>Derecho de rectificación:</strong> solicitar la corrección de datos inexactos o completar los que sean incompletos.</li>
<li><strong>Derecho de supresión:</strong> solicitar la eliminación de sus datos cuando, entre otros motivos, ya no sean necesarios para los fines para los que fueron recogidos.</li>
<li><strong>Derecho a la portabilidad:</strong> recibir los datos que nos haya proporcionado en un formato estructurado y de uso común.</li>
<li><strong>Derecho de limitación del tratamiento:</strong> solicitar la limitación del tratamiento de sus datos en determinadas circunstancias.</li>
<li><strong>Derecho de oposición:</strong> oponerse al tratamiento de sus datos por motivos relacionados con su situación particular.</li>
<li><strong>Derecho a no ser objeto de decisiones automatizadas:</strong> no ser objeto de decisiones basadas únicamente en el tratamiento automatizado, incluida la elaboración de perfiles.</li>
<li><strong>Derecho a retirar el consentimiento:</strong> retirar el consentimiento prestado en cualquier momento, sin que ello afecte a la licitud del tratamiento basado en el consentimiento previo a su retirada.</li>
</ul>
<p>Para ejercer cualquiera de estos derechos, puede dirigirse a {{email_dpo}} indicando el derecho que desea ejercer y acompañando copia de su DNI o documento identificativo equivalente.</p>
<p>Asimismo, le informamos de su derecho a presentar una reclamación ante la Agencia Española de Protección de Datos (AEPD), con sede en C/ Jorge Juan, 6, 28001 Madrid, si considera que el tratamiento de sus datos no se ajusta a la normativa vigente. Sitio web: <em>www.aepd.es</em></p>

<h2>12. Protección de menores</h2>
<p>Conforme al artículo 7 de la LOPDGDD, el tratamiento de datos personales de menores de 14 años requerirá el consentimiento de sus padres o tutores legales. La asociación no recopilará intencionadamente datos de menores de 14 años sin el consentimiento verificable de sus padres o tutores.</p>

<h2>13. Medidas de seguridad</h2>
<p>Hemos adoptado las medidas técnicas y organizativas apropiadas para garantizar un nivel de seguridad adecuado al riesgo, conforme al artículo 32 del RGPD, incluyendo entre otras:</p>
<ul>
<li>Cifrado de las comunicaciones mediante protocolo SSL/TLS.</li>
<li>Control de acceso a los datos mediante credenciales seguras.</li>
<li>Copias de seguridad periódicas.</li>
<li>Revisiones periódicas de los sistemas de información.</li>
</ul>

<h2>14. Modificaciones de la política de privacidad</h2>
<p>Nos reservamos el derecho de modificar la presente política de privacidad para adaptarla a novedades legislativas o jurisprudenciales. En tales supuestos, se anunciará en esta página los cambios introducidos con razonable antelación a su puesta en práctica.</p>
HTML;
    }

    private function getDefaultNoticeContent(): string
    {
        return <<<'HTML'
<h2>1. Datos identificativos del titular (art. 10 LSSI-CE)</h2>
<p>En cumplimiento del artículo 10 de la Ley 34/2002, de 11 de julio, de servicios de la sociedad de la información y de comercio electrónico (LSSI-CE), se informa a los usuarios de los siguientes datos:</p>
<p><strong>Titular:</strong> {{nombre}}<br>
CIF/NIF: {{cif}}<br>
Domicilio social: {{direccion}}<br>
Correo electrónico: {{email}}<br>
Teléfono: {{telefono}}<br>
Datos registrales: {{registro}}</p>

<h2>2. Objeto del sitio web</h2>
<p>El presente sitio web, accesible desde {{dominio}}, es titularidad de {{nombre}} y tiene como finalidad la difusión de las actividades, eventos y contenidos de la asociación, así como facilitar la comunicación e interacción con sus socios y el público en general.</p>

<h2>3. Condiciones de uso</h2>
<p>El acceso al sitio web atribuye la condición de usuario e implica la aceptación plena y sin reservas de todas y cada una de las disposiciones incluidas en este aviso legal. El usuario se compromete a hacer un uso adecuado del sitio web, de conformidad con la ley, el presente aviso legal, las buenas costumbres y el orden público.</p>
<p>El usuario se obliga a abstenerse de utilizar el sitio web con fines ilícitos o contrarios a lo establecido en este aviso legal, que resulten lesivos de los derechos e intereses de terceros, o que de cualquier forma puedan dañar, inutilizar, sobrecargar o deteriorar el sitio web o impedir la normal utilización por parte de otros usuarios.</p>

<h2>4. Propiedad intelectual e industrial</h2>
<p>Todos los contenidos del sitio web, incluyendo a título enunciativo pero no limitativo, textos, fotografías, gráficos, imágenes, logotipos, iconos, tecnología, software, enlaces y demás contenidos audiovisuales o sonoros, así como su diseño gráfico y códigos fuente, son propiedad intelectual de {{nombre}} o de terceros que han autorizado su uso, sin que puedan entenderse cedidos al usuario ninguno de los derechos de explotación sobre los mismos más allá de lo estrictamente necesario para el correcto uso del sitio web.</p>
<p>Quedan expresamente prohibidas la reproducción, distribución, comunicación pública y transformación de la totalidad o parte de los contenidos de este sitio web, con fines comerciales, en cualquier soporte y por cualquier medio técnico, sin la autorización expresa y por escrito de {{nombre}}.</p>

<h2>5. Enlaces a terceros</h2>
<p>El sitio web puede contener enlaces a páginas de terceros. {{nombre}} no asume ninguna responsabilidad por el contenido, informaciones o servicios que pudieran aparecer en dichos sitios, que tendrán exclusivamente carácter informativo y que en ningún caso implican relación alguna entre {{nombre}} y las personas o entidades titulares de tales contenidos.</p>

<h2>6. Exclusión de responsabilidad</h2>
<p>{{nombre}} no se hace responsable de:</p>
<ul>
<li>Los daños o perjuicios que pudieran derivarse de interferencias, omisiones, interrupciones, virus informáticos, averías o desconexiones en el funcionamiento operativo del sistema electrónico.</li>
<li>Los retrasos o bloqueos en el uso del sistema causados por deficiencias o sobrecargas en las líneas telefónicas, en el sistema de internet o en otros sistemas electrónicos.</li>
<li>La falta de disponibilidad o continuidad del funcionamiento del sitio web y de los servicios.</li>
<li>Los errores o inexactitudes que pudieran presentar los contenidos del sitio web, si bien se realizarán los esfuerzos oportunos para evitarlos y corregirlos en el menor tiempo posible.</li>
</ul>

<h2>7. Protección de datos</h2>
<p>{{nombre}} cumple con la normativa de protección de datos personales, conforme al Reglamento (UE) 2016/679 (RGPD) y la Ley Orgánica 3/2018 (LOPDGDD). Para más información, consulte nuestra política de privacidad.</p>

<h2>8. Legislación aplicable y jurisdicción</h2>
<p>Las presentes condiciones se rigen por la legislación española. Para la resolución de cualquier controversia que pudiera derivarse del acceso o uso de este sitio web, {{nombre}} y el usuario se someten a los Juzgados y Tribunales del domicilio del usuario, siempre que este sea consumidor. En caso contrario, se someten a los Juzgados y Tribunales correspondientes al domicilio de {{nombre}}.</p>
HTML;
    }

    private function getDefaultCookiesContent(): string
    {
        return <<<'HTML'
<h2>1. ¿Qué son las cookies?</h2>
<p>Las cookies son pequeños archivos de texto que los sitios web que visita envían al navegador y que se almacenan en el dispositivo del usuario (ordenador, teléfono móvil, tableta, etc.). Estos archivos permiten que el sitio web recuerde información sobre su visita, como su idioma preferido y otras opciones, lo que puede facilitar su próxima visita y hacer que el sitio le resulte más útil.</p>

<h2>2. Tipos de cookies</h2>
<p>Según su finalidad, las cookies pueden clasificarse en:</p>
<ul>
<li><strong>Cookies técnicas o necesarias:</strong> son esenciales para el funcionamiento del sitio web y permiten funcionalidades básicas como la navegación entre páginas, el acceso a áreas seguras o el mantenimiento de la sesión del usuario.</li>
<li><strong>Cookies analíticas:</strong> permiten al titular del sitio web el seguimiento y análisis estadístico del comportamiento de los usuarios. La información recogida mediante estas cookies se utiliza para medir la actividad del sitio web y para la elaboración de perfiles de navegación.</li>
<li><strong>Cookies funcionales:</strong> permiten recordar las preferencias del usuario para ofrecer una experiencia más personalizada, como el idioma, la configuración regional o el tipo de navegador.</li>
</ul>
<p>Según su duración:</p>
<ul>
<li><strong>Cookies de sesión:</strong> se eliminan al cerrar el navegador.</li>
<li><strong>Cookies persistentes:</strong> permanecen almacenadas durante un periodo determinado o hasta que el usuario las elimine.</li>
</ul>
<p>Según quién las gestione:</p>
<ul>
<li><strong>Cookies propias:</strong> gestionadas directamente por {{dominio}}.</li>
<li><strong>Cookies de terceros:</strong> gestionadas por un dominio distinto al del sitio web.</li>
</ul>

<h2>3. Cookies utilizadas en este sitio web</h2>
<p>A continuación se detallan las cookies utilizadas en {{dominio}}:</p>
<table>
<thead>
<tr><th>Nombre</th><th>Tipo</th><th>Finalidad</th><th>Duración</th></tr>
</thead>
<tbody>
<tr><td>XSRF-TOKEN</td><td>Técnica (propia)</td><td>Protección contra falsificación de solicitudes (CSRF)</td><td>Sesión</td></tr>
<tr><td>session</td><td>Técnica (propia)</td><td>Identificación de la sesión del usuario</td><td>Sesión</td></tr>
<tr><td>remember_web_*</td><td>Funcional (propia)</td><td>Recordar la sesión del usuario entre visitas</td><td>5 años</td></tr>
</tbody>
</table>

<h2>4. Cookies de terceros</h2>
<p>Este sitio web puede utilizar servicios de terceros que, a su vez, podrán instalar cookies en su dispositivo para prestar sus servicios. En caso de utilizarse herramientas de análisis web o servicios externos, se informará debidamente en esta sección. {{nombre}} no tiene control sobre las cookies establecidas por terceros.</p>

<h2>5. Gestión y desactivación de cookies</h2>
<p>Puede permitir, bloquear o eliminar las cookies instaladas en su dispositivo mediante la configuración de las opciones de su navegador. A continuación le indicamos cómo hacerlo en los principales navegadores:</p>
<ul>
<li><strong>Google Chrome:</strong> Configuración → Privacidad y seguridad → Cookies y otros datos de sitios.</li>
<li><strong>Mozilla Firefox:</strong> Configuración → Privacidad y seguridad → Cookies y datos del sitio.</li>
<li><strong>Safari:</strong> Preferencias → Privacidad → Gestionar datos de sitios web.</li>
<li><strong>Microsoft Edge:</strong> Configuración → Cookies y permisos del sitio → Cookies y datos almacenados.</li>
</ul>

<h2>6. Consecuencias de desactivar las cookies</h2>
<p>Si desactiva las cookies técnicas o necesarias, es posible que algunas funcionalidades del sitio web dejen de estar disponibles o que su experiencia de navegación se vea afectada. Por ejemplo, podría no mantener su sesión iniciada al navegar entre páginas.</p>

<h2>7. Actualización de la política de cookies</h2>
<p>{{nombre}} puede modificar esta política de cookies en función de cambios legislativos, regulatorios o con la finalidad de adaptar dicha política a las instrucciones dictadas por la Agencia Española de Protección de Datos. Cualquier modificación será publicada en esta página.</p>

<h2>8. Más información</h2>
<p>Si tiene dudas o preguntas sobre esta política de cookies, puede ponerse en contacto con nosotros a través del correo electrónico {{email}} o {{email_dpo}}.</p>
HTML;
    }

    private function getDefaultTermsContent(): string
    {
        return <<<'HTML'
<h2>1. Información general</h2>
<p>Las presentes condiciones regulan el uso del sitio web {{dominio}}, titularidad de {{nombre}} (en adelante, «la asociación»), con CIF {{cif}} y domicilio social en {{direccion}}.</p>
<p>Datos de contacto: {{email}} | {{telefono}}<br>
Datos registrales: {{registro}}</p>

<h2>2. Aceptación de las condiciones</h2>
<p>El acceso y uso de este sitio web implica la aceptación expresa y sin reservas de todas las condiciones incluidas en el presente documento, así como en el aviso legal, la política de privacidad y la política de cookies. Si no está de acuerdo con alguna de estas condiciones, le rogamos que no utilice este sitio web.</p>

<h2>3. Acceso y uso del sitio web</h2>
<p>El acceso al sitio web es gratuito, salvo en lo relativo al coste de la conexión a internet suministrada por el proveedor de acceso contratado por el usuario. Determinados contenidos o servicios podrán requerir el registro previo del usuario.</p>
<p>La asociación se reserva el derecho de modificar en cualquier momento la presentación, configuración y contenidos del sitio web, así como las condiciones requeridas para su acceso y uso.</p>

<h2>4. Registro de usuarios</h2>
<p>Para acceder a determinadas funcionalidades del sitio web, el usuario deberá registrarse proporcionando información veraz, exacta, actualizada y completa. El usuario será responsable de mantener la confidencialidad de sus credenciales de acceso y de todas las actividades que se realicen bajo su cuenta.</p>
<p>El usuario se compromete a notificar de inmediato cualquier uso no autorizado de su cuenta o cualquier otra brecha de seguridad.</p>
<p>La asociación se reserva el derecho de suspender o cancelar cuentas de usuario que incumplan las presentes condiciones.</p>

<h2>5. Obligaciones del usuario</h2>
<p>El usuario se compromete a:</p>
<ul>
<li>Hacer un uso adecuado y lícito del sitio web, de conformidad con la legislación vigente, la moral, las buenas costumbres y el orden público.</li>
<li>No introducir o difundir contenidos de carácter racista, xenófobo, pornográfico, de apología del terrorismo, que atenten contra los derechos humanos o contra los derechos de los menores de edad.</li>
<li>No introducir virus, programas maliciosos u otros elementos que puedan causar alteraciones en los sistemas informáticos de la asociación o de terceros.</li>
<li>No intentar acceder a áreas restringidas del sitio web sin la debida autorización.</li>
<li>No suplantar la identidad de otros usuarios.</li>
<li>Respetar los derechos de propiedad intelectual e industrial de la asociación y de terceros.</li>
</ul>

<h2>6. Propiedad intelectual</h2>
<p>Todos los contenidos del sitio web (textos, fotografías, gráficos, imágenes, logotipos, tecnología, software y demás contenidos) son propiedad de la asociación o de terceros que han autorizado su uso. Queda prohibida su reproducción, distribución, comunicación pública o transformación sin autorización expresa, salvo para uso personal y privado.</p>

<h2>7. Publicación de contenidos por usuarios</h2>
<p>La asociación podrá publicar fotografías y contenidos audiovisuales de los eventos y actividades en los que participen los usuarios. La publicación de estos contenidos se realizará conforme a la normativa de protección de datos y protección del derecho a la propia imagen.</p>
<p>Los usuarios que publiquen contenidos en el sitio web (comentarios, foros u otros espacios habilitados) serán responsables de los mismos, garantizando que no vulneran la legislación vigente ni los derechos de terceros. La asociación se reserva el derecho de retirar aquellos contenidos que considere inapropiados o contrarios a estas condiciones.</p>

<h2>8. Protección de datos</h2>
<p>El tratamiento de los datos personales de los usuarios se rige por nuestra política de privacidad, disponible en este mismo sitio web. El uso de este sitio web implica el conocimiento y aceptación de dicha política.</p>

<h2>9. Modificación de las condiciones</h2>
<p>La asociación se reserva el derecho de modificar las presentes condiciones de uso en cualquier momento. Las modificaciones serán publicadas en esta página y entrarán en vigor desde su publicación. El uso continuado del sitio web tras la publicación de las modificaciones implicará la aceptación de las nuevas condiciones.</p>

<h2>10. Nulidad parcial</h2>
<p>Si cualquier cláusula de las presentes condiciones fuera declarada nula o inaplicable, dicha cláusula será excluida sin que ello afecte a la validez y aplicabilidad del resto de condiciones.</p>

<h2>11. Legislación aplicable y jurisdicción</h2>
<p>Las presentes condiciones se rigen por la legislación española. Para la resolución de cualquier controversia derivada del uso de este sitio web, {{nombre}} y el usuario se someten a los Juzgados y Tribunales del domicilio del usuario, siempre que este tenga la condición de consumidor. En caso contrario, ambas partes se someten a los Juzgados y Tribunales correspondientes al domicilio de la asociación.</p>
HTML;
    }
}
