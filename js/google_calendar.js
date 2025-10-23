/**
 * Google Calendar Client-side Integration
 * ไฟล์: js/google_calendar.js
 * จัดการการเชื่อมต่อและซิงค์กับ Google Calendar ฝั่ง client
 */

class GoogleCalendarClient {
    constructor(config) {
        this.config = {
            clientId: config.clientId,
            apiKey: config.apiKey,
            discoveryDocs: ['https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest'],
            scopes: 'https://www.googleapis.com/auth/calendar'
        };
        
        this.isSignedIn = false;
        this.currentUser = null;
        this.calendarService = null;
        
        this.init();
    }
    
    /**
     * เริ่มต้น Google APIs
     */
    async init() {
        try {
            await this.loadGoogleAPI();
            await this.initializeGAPI();
            this.setupAuthListener();
            
            console.log('Google Calendar Client initialized successfully');
        } catch (error) {
            console.error('Failed to initialize Google Calendar Client:', error);
            throw error;
        }
    }
    
    /**
     * โหลด Google API
     */
    loadGoogleAPI() {
        return new Promise((resolve, reject) => {
            if (typeof gapi !== 'undefined') {
                resolve();
            } else {
                const script = document.createElement('script');
                script.src = 'https://apis.google.com/js/api.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            }
        });
    }
    
    /**
     * เริ่มต้น GAPI Client
     */
    async initializeGAPI() {
        return new Promise((resolve, reject) => {
            gapi.load('client:auth2', async () => {
                try {
                    await gapi.client.init({
                        apiKey: this.config.apiKey,
                        clientId: this.config.clientId,
                        discoveryDocs: this.config.discoveryDocs,
                        scope: this.config.scopes
                    });
                    
                    this.authInstance = gapi.auth2.getAuthInstance();
                    this.isSignedIn = this.authInstance.isSignedIn.get();
                    
                    if (this.isSignedIn) {
                        this.currentUser = this.authInstance.currentUser.get();
                    }
                    
                    resolve();
                } catch (error) {
                    reject(error);
                }
            });
        });
    }
    
    /**
     * ตั้งค่า Auth Listener
     */
    setupAuthListener() {
        this.authInstance.isSignedIn.listen((isSignedIn) => {
            this.isSignedIn = isSignedIn;
            
            if (isSignedIn) {
                this.currentUser = this.authInstance.currentUser.get();
                this.onSignIn();
            } else {
                this.currentUser = null;
                this.onSignOut();
            }
        });
    }
    
    /**
     * เข้าสู่ระบบ Google
     */
    async signIn() {
        try {
            if (!this.isSignedIn) {
                await this.authInstance.signIn();
            }
            return true;
        } catch (error) {
            console.error('Sign in failed:', error);
            return false;
        }
    }
    
    /**
     * ออกจากระบบ Google
     */
    async signOut() {
        try {
            if (this.isSignedIn) {
                await this.authInstance.signOut();
            }
            return true;
        } catch (error) {
            console.error('Sign out failed:', error);
            return false;
        }
    }
    
    /**
     * เมื่อเข้าสู่ระบบสำเร็จ
     */
    onSignIn() {
        console.log('User signed in to Google Calendar');
        this.updateConnectionStatus(true);
        this.enableSyncButtons();
        
        // แสดงข้อมูลผู้ใช้
        if (this.currentUser) {
            const profile = this.currentUser.getBasicProfile();
            const userInfo = {
                name: profile.getName(),
                email: profile.getEmail(),
                image: profile.getImageUrl()
            };
            this.displayUserInfo(userInfo);
        }
    }
    
    /**
     * เมื่อออกจากระบบ
     */
    onSignOut() {
        console.log('User signed out from Google Calendar');
        this.updateConnectionStatus(false);
        this.disableSyncButtons();
    }
    
    /**
     * อัปเดตสถานะการเชื่อมต่อ
     */
    updateConnectionStatus(connected) {
        const statusElement = document.getElementById('connectionStatus');
        if (!statusElement) return;
        
        if (connected) {
            statusElement.className = 'status-indicator status-connected';
            statusElement.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <span>เชื่อมต่อกับ Google Calendar แล้ว</span>
            `;
        } else {
            statusElement.className = 'status-indicator status-disconnected';
            statusElement.innerHTML = `
                <i class="fas fa-times-circle"></i>
                <span>ยังไม่ได้เชื่อมต่อกับ Google Calendar</span>
            `;
        }
    }
    
    /**
     * เปิดใช้งานปุ่มซิงค์
     */
    enableSyncButtons() {
        const buttons = document.querySelectorAll('.sync-btn, #syncBtn');
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
        });
    }
    
    /**
     * ปิดใช้งานปุ่มซิงค์
     */
    disableSyncButtons() {
        const buttons = document.querySelectorAll('.sync-btn, #syncBtn');
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        });
    }
    
    /**
     * แสดงข้อมูลผู้ใช้
     */
    displayUserInfo(userInfo) {
        const userInfoElement = document.getElementById('googleUserInfo');
        if (userInfoElement) {
            userInfoElement.innerHTML = `
                <div class="google-user-info">
                    <img src="${userInfo.image}" alt="Profile" class="user-avatar">
                    <div class="user-details">
                        <div class="user-name">${userInfo.name}</div>
                        <div class="user-email">${userInfo.email}</div>
                    </div>
                </div>
            `;
        }
    }
    
    /**
     * ซิงค์ข้อมูลไป Google Calendar
     */
    async syncToCalendar(eventsData) {
        if (!this.isSignedIn) {
            throw new Error('ยังไม่ได้เข้าสู่ระบบ Google Calendar');
        }
        
        try {
            const results = [];
            
            for (let i = 0; i < eventsData.length; i++) {
                const eventData = eventsData[i];
                
                // แสดงความคืบหน้า
                this.updateSyncProgress(i + 1, eventsData.length);
                
                try {
                    const result = await this.createCalendarEvent(eventData);
                    results.push({
                        success: true,
                        eventId: result.id,
                        eventData: eventData
                    });
                } catch (error) {
                    console.error('Failed to create event:', error);
                    results.push({
                        success: false,
                        error: error.message,
                        eventData: eventData
                    });
                }
                
                // หน่วงเวลาเล็กน้อยเพื่อไม่ให้เกิน rate limit
                await this.delay(100);
            }
            
            return {
                success: true,
                results: results,
                totalEvents: eventsData.length,
                successfulEvents: results.filter(r => r.success).length
            };
            
        } catch (error) {
            throw new Error('เกิดข้อผิดพลาดในการซิงค์: ' + error.message);
        }
    }
    
    /**
     * สร้าง event ใน Google Calendar
     */
    async createCalendarEvent(eventData) {
        const request = gapi.client.calendar.events.insert({
            calendarId: 'primary',
            resource: eventData
        });
        
        const response = await request;
        return response.result;
    }
    
    /**
     * ลบ events ที่สร้างโดยระบบ
     */
    async clearSystemEvents() {
        if (!this.isSignedIn) {
            throw new Error('ยังไม่ได้เข้าสู่ระบบ Google Calendar');
        }
        
        try {
            // ค้นหา events ที่สร้างโดยระบบ
            const response = await gapi.client.calendar.events.list({
                calendarId: 'primary',
                q: 'source:teaching_schedule_system',
                maxResults: 2500
            });
            
            const events = response.result.items || [];
            let deletedCount = 0;
            
            for (const event of events) {
                try {
                    await gapi.client.calendar.events.delete({
                        calendarId: 'primary',
                        eventId: event.id
                    });
                    deletedCount++;
                    await this.delay(50);
                } catch (error) {
                    console.error(`Failed to delete event ${event.id}:`, error);
                }
            }
            
            return {
                success: true,
                deletedCount: deletedCount
            };
            
        } catch (error) {
            throw new Error('เกิดข้อผิดพลาดในการลบ events: ' + error.message);
        }
    }
    
    /**
     * อัปเดตความคืบหน้าการซิงค์
     */
    updateSyncProgress(current, total) {
        const progressElement = document.getElementById('syncProgress');
        if (progressElement) {
            const percentage = Math.round((current / total) * 100);
            progressElement.innerHTML = `
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${percentage}%"></div>
                </div>
                <div class="progress-text">กำลังซิงค์... ${current}/${total} (${percentage}%)</div>
            `;
        }
    }
    
    /**
     * ดึงข้อมูลปฏิทินจาก Google Calendar
     */
    async getCalendarEvents(timeMin, timeMax) {
        if (!this.isSignedIn) {
            return [];
        }
        
        try {
            const response = await gapi.client.calendar.events.list({
                calendarId: 'primary',
                timeMin: timeMin,
                timeMax: timeMax,
                showDeleted: false,
                singleEvents: true,
                maxResults: 2500,
                orderBy: 'startTime'
            });
            
            return response.result.items || [];
        } catch (error) {
            console.error('Failed to get calendar events:', error);
            return [];
        }
    }
    
    /**
     * ตรวจสอบว่ามี event ซ้ำหรือไม่
     */
    async checkDuplicateEvents(eventData) {
        const startTime = eventData.start.dateTime || eventData.start.date;
        const endTime = eventData.end.dateTime || eventData.end.date;
        
        const existingEvents = await this.getCalendarEvents(startTime, endTime);
        
        return existingEvents.some(event => {
            const extendedProps = event.extendedProperties?.private;
            return extendedProps?.source === 'teaching_schedule_system' &&
                   extendedProps?.schedule_id === eventData.extendedProperties?.private?.schedule_id;
        });
    }
    
    /**
     * หน่วงเวลา
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    /**
     * รับ access token ปัจจุบัน
     */
    getAccessToken() {
        if (this.isSignedIn && this.currentUser) {
            return this.currentUser.getAuthResponse().access_token;
        }
        return null;
    }
    
    /**
     * ตรวจสอบสถานะการเชื่อมต่อ
     */
    isConnected() {
        return this.isSignedIn;
    }
}

/**
 * ฟังก์ชันช่วยเหลือสำหรับการใช้งาน
 */

// ตัวแปรสำหรับเก็บ instance
let googleCalendarClient = null;

/**
 * เริ่มต้น Google Calendar Client
 */
async function initializeGoogleCalendar(config) {
    try {
        googleCalendarClient = new GoogleCalendarClient(config);
        return true;
    } catch (error) {
        console.error('Failed to initialize Google Calendar:', error);
        return false;
    }
}

/**
 * เชื่อมต่อ Google Calendar
 */
async function connectGoogleCalendar() {
    if (!googleCalendarClient) {
        throw new Error('Google Calendar Client ยังไม่ได้เริ่มต้น');
    }
    
    return await googleCalendarClient.signIn();
}

/**
 * ยกเลิกการเชื่อมต่อ Google Calendar
 */
async function disconnectGoogleCalendar() {
    if (!googleCalendarClient) {
        return true;
    }
    
    return await googleCalendarClient.signOut();
}

/**
 * ซิงค์ตารางสอนไป Google Calendar
 */
async function syncSchedulesToGoogleCalendar() {
    if (!googleCalendarClient || !googleCalendarClient.isConnected()) {
        throw new Error('ยังไม่ได้เชื่อมต่อกับ Google Calendar');
    }
    
    try {
        // แสดง loading
        showSyncLoading(true);
        
        // ดึงข้อมูลตารางสอนจาก server
        const response = await fetch('api/get_schedule_data.php');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'ไม่สามารถดึงข้อมูลตารางสอนได้');
        }
        
        const eventsData = data.data.events || [];
        
        if (eventsData.length === 0) {
            showSyncResult('ไม่มีข้อมูลตารางสอนที่ต้องซิงค์', 'info');
            return;
        }
        
        // ซิงค์ไป Google Calendar
        const result = await googleCalendarClient.syncToCalendar(eventsData);
        
        if (result.success) {
            const message = `ซิงค์เรียบร้อยแล้ว: ${result.successfulEvents}/${result.totalEvents} รายการ`;
            showSyncResult(message, 'success');
            
            // อัปเดตข้อมูลการซิงค์
            updateSyncInfo({
                lastSync: new Date().toLocaleString('th-TH'),
                syncedEvents: result.successfulEvents,
                totalEvents: result.totalEvents
            });
            
            // รีเฟรชปฏิทิน
            refreshCalendarDisplay();
        } else {
            throw new Error('การซิงค์ล้มเหลว');
        }
        
    } catch (error) {
        console.error('Sync error:', error);
        showSyncResult('เกิดข้อผิดพลาด: ' + error.message, 'error');
    } finally {
        showSyncLoading(false);
    }
}

/**
 * ลบ events ในปฏิทินที่สร้างโดยระบบ
 */
async function clearGoogleCalendarEvents() {
    if (!googleCalendarClient || !googleCalendarClient.isConnected()) {
        throw new Error('ยังไม่ได้เชื่อมต่อกับ Google Calendar');
    }
    
    try {
        showSyncLoading(true, 'กำลังลบ events...');
        
        const result = await googleCalendarClient.clearSystemEvents();
        
        if (result.success) {
            showSyncResult(`ลบ events เรียบร้อยแล้ว: ${result.deletedCount} รายการ`, 'success');
            refreshCalendarDisplay();
        }
        
    } catch (error) {
        console.error('Clear events error:', error);
        showSyncResult('เกิดข้อผิดพลาด: ' + error.message, 'error');
    } finally {
        showSyncLoading(false);
    }
}

/**
 * รีเฟรชการแสดงปฏิทิน
 */
function refreshCalendarDisplay() {
    const calendarFrame = document.getElementById('calendar-frame');
    if (calendarFrame) {
        // รีเฟรช iframe
        calendarFrame.src = calendarFrame.src;
    }
}

/**
 * แสดง loading สำหรับการซิงค์
 */
function showSyncLoading(show, message = 'กำลังซิงค์...') {
    const loadingElement = document.getElementById('syncLoading');
    
    if (show) {
        if (!loadingElement) {
            const loading = document.createElement('div');
            loading.id = 'syncLoading';
            loading.className = 'sync-loading';
            loading.innerHTML = `
                <div class="loading-backdrop">
                    <div class="loading-content">
                        <i class="fas fa-spinner fa-spin"></i>
                        <h3>${message}</h3>
                        <div id="syncProgress"></div>
                    </div>
                </div>
            `;
            loading.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            document.body.appendChild(loading);
        } else {
            loadingElement.style.display = 'flex';
            loadingElement.querySelector('h3').textContent = message;
        }
    } else {
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }
    }
}

/**
 * แสดงผลลัพธ์การซิงค์
 */
function showSyncResult(message, type = 'info') {
    const colors = {
        success: '#4caf50',
        error: '#f44336',
        info: '#2196f3',
        warning: '#ff9800'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
    };
    
    const toast = document.createElement('div');
    toast.className = 'sync-result-toast';
    toast.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span>${message}</span>
    `;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type]};
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 400px;
        word-wrap: break-word;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 5000);
}

/**
 * อัปเดตข้อมูลการซิงค์
 */
function updateSyncInfo(syncData) {
    const syncInfoElement = document.getElementById('syncInfo');
    if (syncInfoElement) {
        syncInfoElement.style.display = 'block';
        
        const lastSyncElement = document.getElementById('lastSyncTime');
        const syncedEventsElement = document.getElementById('syncedEvents');
        const syncStatusElement = document.getElementById('syncStatus');
        
        if (lastSyncElement) lastSyncElement.textContent = syncData.lastSync;
        if (syncedEventsElement) syncedEventsElement.textContent = syncData.syncedEvents;
        if (syncStatusElement) syncStatusElement.textContent = 'ซิงค์เรียบร้อยแล้ว';
    }
}

/**
 * ตรวจสอบสถานะการเชื่อมต่อ Google Calendar
 */
function checkGoogleCalendarConnection() {
    return googleCalendarClient && googleCalendarClient.isConnected();
}

/**
 * ดึงข้อมูลผู้ใช้ Google ที่ล็อกอิน
 */
function getGoogleUserInfo() {
    if (googleCalendarClient && googleCalendarClient.isConnected()) {
        const user = googleCalendarClient.currentUser;
        if (user) {
            const profile = user.getBasicProfile();
            return {
                name: profile.getName(),
                email: profile.getEmail(),
                image: profile.getImageUrl()
            };
        }
    }
    return null;
}

/**
 * เพิ่ม CSS สำหรับ sync loading
 */
function addSyncLoadingStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .loading-backdrop {
            background: rgba(0, 0, 0, 0.7);
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .loading-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            min-width: 300px;
        }
        
        .loading-content i {
            font-size: 3rem;
            color: #4285f4;
            margin-bottom: 20px;
        }
        
        .loading-content h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4285f4, #34a853);
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: 0.9rem;
            color: #666;
        }
    `;
    document.head.appendChild(style);
}

// เพิ่ม styles เมื่อโหลดไฟล์
addSyncLoadingStyles();