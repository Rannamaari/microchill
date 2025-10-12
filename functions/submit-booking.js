export async function onRequestPost(context) {
  const { request, env } = context;

  try {
    // Parse the form data
    const formData = await request.json();

    // Verify reCAPTCHA
    const recaptchaVerification = await fetch('https://www.google.com/recaptcha/api/siteverify', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `secret=${env.RECAPTCHA_SECRET_KEY}&response=${formData.recaptchaToken}`
    });

    const recaptchaResult = await recaptchaVerification.json();

    if (!recaptchaResult.success) {
      return new Response(JSON.stringify({ error: 'reCAPTCHA verification failed' }), {
        status: 400,
        headers: { 'Content-Type': 'application/json' }
      });
    }

    // Build address from components
    const address = [
      formData.house,
      formData.road,
      formData.area,
      formData.floor ? `Floor: ${formData.floor}` : ''
    ].filter(Boolean).join(', ');

    // Create Telegram message
    const message = `🆕 New Booking Request from MicroCool Website\n\n` +
      `👤 Name: ${formData.name}\n` +
      `📞 Phone: ${formData.phone}\n` +
      `📧 Email: ${formData.email || 'Not provided'}\n` +
      `📍 Address: ${address}\n` +
      `📅 Date: ${formData.date}\n` +
      `⏰ Time Slot: ${formData.timeSlot || 'Anytime'}\n` +
      `🔧 Service: ${formData.service}\n` +
      `❄️ AC Brand: ${formData.brand || 'Not specified'}\n` +
      `📊 BTU: ${formData.btu || 'Not specified'}\n` +
      `📝 Notes: ${formData.notes || 'None'}\n` +
      `🛡️ Verified: ✅ Human verified (reCAPTCHA passed)`;

    // Send to Telegram
    const telegramResponse = await fetch(`https://api.telegram.org/bot${env.TELEGRAM_BOT_TOKEN}/sendMessage`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        chat_id: env.TELEGRAM_CHAT_ID,
        text: message,
        parse_mode: 'HTML'
      })
    });

    if (!telegramResponse.ok) {
      throw new Error('Failed to send Telegram message');
    }

    return new Response(JSON.stringify({
      success: true,
      message: 'Booking request sent successfully'
    }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' }
    });

  } catch (error) {
    console.error('Error processing booking:', error);
    return new Response(JSON.stringify({
      error: 'Internal server error',
      message: 'Failed to process booking request'
    }), {
      status: 500,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}
